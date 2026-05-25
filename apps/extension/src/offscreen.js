const video = document.getElementById("cam");
let faceLandmarker = null;

const SAMPLE_INTERVAL_MS = 150;
const CALIBRATION_MS = 3000;
const AWAY_EVENT_AFTER_MS = 2500;
const ENTER_AWAY_THRESHOLD = 2.7;
const EXIT_AWAY_THRESHOLD = 1.7;
const MAX_POSE_STEP = 2.5;
const SCORE_EWMA_ALPHA = 0.35;

const EYE_OPEN_MIN_RATIO = 0.12;
const EYE_SHIFT_WEIGHT = 2.2;
const EYE_OPENNESS_WEIGHT = 0.35;
const EYE_OVERRIDE_MAX = 3;
const EYE_AWAY_THRESHOLD = 3;
const HEAVY_HEAD_TURN_DEGREES = 14;
const STRONG_HEAD_TURN_DEGREES = 20;
const YAW_TO_SCREEN_GAZE_COMPENSATION = 0.012;
const MAX_SCREEN_GAZE_SHIFT = 0.12;

const SELF_CALIBRATION_MIN_SCORE = 0.9;
const SELF_CALIBRATION_MIN_STABLE_MS = 2500;
const SELF_CALIBRATION_ALPHA = 0.015;
const DEV_LOCAL_SIMULATION = globalThis.__LOOK_DEV_LOCAL_SIMULATION__ === true;
const DEV_SIMULATION_START_AFTER_NO_FACE_MS = 4000;
const DEV_SIMULATION_CYCLE_MS = 9000;
const BOOTSTRAP_RETRY_MS = 5000;
const RESUME_GAP_MS = 15000;

const DEV_LOOKING_POSE = { yaw: 0, pitch: 0, roll: 0 };
const DEV_AWAY_POSE = { yaw: 24, pitch: 0, roll: 0 };
const DEV_EYES = {
  leftOpenRatio: 0.22,
  rightOpenRatio: 0.22,
  leftIrisX: 0.5,
  rightIrisX: 0.5,
  leftOpen: true,
  rightOpen: true,
};

const rad2deg = (r) => (r * 180) / Math.PI;

function eulerFromRotationMatrix(R) {
  const r00 = R[0], r10 = R[3];
  const r20 = R[6], r21 = R[7], r22 = R[8];
  const pitch = Math.atan2(-r21, r22);
  const yaw = Math.asin(r20);
  const roll = Math.atan2(-r10, r00);
  return { yaw, pitch, roll };
}

function toDegreesPose(matrix) {
  const R = [matrix[0], matrix[1], matrix[2], matrix[4], matrix[5], matrix[6], matrix[8], matrix[9], matrix[10]];
  const { yaw, pitch, roll } = eulerFromRotationMatrix(R);
  return { yaw: rad2deg(yaw), pitch: rad2deg(pitch), roll: rad2deg(roll) };
}

function getLandmarkDistance(a, b) {
  if (!a || !b) return null;
  const dx = a.x - b.x;
  const dy = a.y - b.y;
  return Math.sqrt((dx * dx) + (dy * dy));
}

function meanPoint(points) {
  if (!points?.length) return null;
  const total = points.reduce((acc, point) => {
    acc.x += point.x;
    acc.y += point.y;
    return acc;
  }, { x: 0, y: 0 });

  return {
    x: total.x / points.length,
    y: total.y / points.length,
  };
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function computeEyeMetrics(landmarks) {
  if (!landmarks?.length) return null;

  const leftOuter = landmarks[263];
  const leftInner = landmarks[362];
  const rightInner = landmarks[133];
  const rightOuter = landmarks[33];
  const leftUpper = landmarks[386];
  const leftLower = landmarks[374];
  const rightUpper = landmarks[159];
  const rightLower = landmarks[145];
  const leftIris = meanPoint([landmarks[473], landmarks[474], landmarks[475], landmarks[476], landmarks[477]].filter(Boolean));
  const rightIris = meanPoint([landmarks[468], landmarks[469], landmarks[470], landmarks[471], landmarks[472]].filter(Boolean));

  const leftWidth = getLandmarkDistance(leftOuter, leftInner);
  const rightWidth = getLandmarkDistance(rightOuter, rightInner);
  const leftOpen = getLandmarkDistance(leftUpper, leftLower);
  const rightOpen = getLandmarkDistance(rightUpper, rightLower);

  if (!leftWidth || !rightWidth || !leftOpen || !rightOpen || !leftIris || !rightIris) {
    return null;
  }

  return {
    leftOpenRatio: leftOpen / leftWidth,
    rightOpenRatio: rightOpen / rightWidth,
    leftIrisX: 1 - ((leftIris.x - leftOuter.x) / ((leftInner.x - leftOuter.x) || 1)),
    rightIrisX: 1 - ((rightIris.x - rightInner.x) / ((rightOuter.x - rightInner.x) || 1)),
    leftOpen: (leftOpen / leftWidth) >= EYE_OPEN_MIN_RATIO,
    rightOpen: (rightOpen / rightWidth) >= EYE_OPEN_MIN_RATIO,
  };
}

async function startCamera() {
  stopCamera();
  const stream = await navigator.mediaDevices.getUserMedia({ video: true });
  video.srcObject = stream;
  for (const track of stream.getVideoTracks()) {
    track.onended = () => scheduleFullRestart("camera track ended");
    track.onmute = () => scheduleFullRestart("camera track muted");
  }
  await video.play();
}

function stopCamera() {
  const stream = video.srcObject;
  if (stream?.getTracks) {
    for (const track of stream.getTracks()) {
      track.onended = null;
      track.onmute = null;
      track.stop();
    }
  }
  video.srcObject = null;
}

async function loadModel() {
  if (faceLandmarker?.close) {
    try {
      faceLandmarker.close();
    } catch {}
  }

  const vision = await import("./vendor/mp/vision_bundle.mjs");
  const mod = vision.default ?? vision;
  const wasmBase = chrome.runtime.getURL("src/vendor/mp/wasm");
  const fileset = await mod.FilesetResolver.forVisionTasks(wasmBase);
  const modelPath = chrome.runtime.getURL("src/models/face_landmarker.task");

  const options = {
    baseOptions: { modelAssetPath: modelPath, delegate: "GPU" },
    runningMode: "VIDEO",
    numFaces: 1,
    outputFacialTransformationMatrixes: true,
  };

  try {
    faceLandmarker = await mod.FaceLandmarker.createFromOptions(fileset, options);
  } catch (error) {
    faceLandmarker = await mod.FaceLandmarker.createFromOptions(fileset, {
      ...options,
      baseOptions: { modelAssetPath: modelPath, delegate: "CPU" },
    });
  }
}

const state = {
  mode: "starting",
  baseline: null,
  calibrationStartedAt: 0,
  calibrationSamples: [],
  smoothedScore: null,
  lastPose: null,
  lastEyes: null,
  isLooking: true,
  awayStartMs: null,
  awayEventsLocal: 0,
  lastStatePayload: null,
  stableLookingSinceMs: null,
  noFaceSinceMs: null,
  devSimulationActive: false,
  devSimulationStartedAt: null,
  lastLoopAt: null,
};
let devSimulationTimer = null;
let loopTimer = null;
let restartTimer = null;
let bootstrapping = false;
let deviceChangeListenerInstalled = false;

function installDeviceChangeListener() {
  if (deviceChangeListenerInstalled || !navigator.mediaDevices?.addEventListener) {
    return;
  }

  navigator.mediaDevices.addEventListener("devicechange", () => {
    scheduleFullRestart("camera device changed");
  });
  deviceChangeListenerInstalled = true;
}

function beginCalibration(now = Date.now(), options = {}) {
  const preserveSimulation = Boolean(options.preserveSimulation);
  state.mode = "calibrating";
  state.baseline = null;
  state.calibrationStartedAt = now;
  state.calibrationSamples = [];
  state.smoothedScore = null;
  state.lastPose = null;
  state.lastEyes = null;
  state.isLooking = true;
  state.awayStartMs = null;
  state.stableLookingSinceMs = null;
  state.noFaceSinceMs = null;
  if (!preserveSimulation) {
    state.devSimulationActive = false;
    state.devSimulationStartedAt = null;
  }
  state.lastStatePayload = null;
}

function averageCalibration(samples) {
  const total = samples.reduce((acc, sample) => {
    acc.yaw += sample.pose.yaw;
    acc.pitch += sample.pose.pitch;
    acc.roll += sample.pose.roll;
    if (sample.eyes) {
      acc.leftOpenRatio += sample.eyes.leftOpenRatio;
      acc.rightOpenRatio += sample.eyes.rightOpenRatio;
      acc.leftIrisX += sample.eyes.leftIrisX;
      acc.rightIrisX += sample.eyes.rightIrisX;
      acc.eyeSamples += 1;
    }
    return acc;
  }, {
    yaw: 0,
    pitch: 0,
    roll: 0,
    leftOpenRatio: 0,
    rightOpenRatio: 0,
    leftIrisX: 0,
    rightIrisX: 0,
    eyeSamples: 0,
  });

  return {
    yaw: total.yaw / samples.length,
    pitch: total.pitch / samples.length,
    roll: total.roll / samples.length,
    leftOpenRatio: total.eyeSamples ? total.leftOpenRatio / total.eyeSamples : null,
    rightOpenRatio: total.eyeSamples ? total.rightOpenRatio / total.eyeSamples : null,
    leftIrisX: total.eyeSamples ? total.leftIrisX / total.eyeSamples : null,
    rightIrisX: total.eyeSamples ? total.rightIrisX / total.eyeSamples : null,
    eyeSamples: total.eyeSamples,
  };
}

function clampPoseJump(pose) {
  if (!state.lastPose) {
    state.lastPose = pose;
    return pose;
  }

  const stabilized = {
    yaw: state.lastPose.yaw + clamp(pose.yaw - state.lastPose.yaw, -MAX_POSE_STEP, MAX_POSE_STEP),
    pitch: state.lastPose.pitch + clamp(pose.pitch - state.lastPose.pitch, -MAX_POSE_STEP, MAX_POSE_STEP),
    roll: state.lastPose.roll + clamp(pose.roll - state.lastPose.roll, -MAX_POSE_STEP, MAX_POSE_STEP),
  };

  state.lastPose = stabilized;
  return stabilized;
}

function smoothEyes(eyes) {
  if (!eyes) return null;
  if (!state.lastEyes) {
    state.lastEyes = eyes;
    return eyes;
  }

  const alpha = 0.4;
  const stabilized = {
    leftOpenRatio: alpha * eyes.leftOpenRatio + (1 - alpha) * state.lastEyes.leftOpenRatio,
    rightOpenRatio: alpha * eyes.rightOpenRatio + (1 - alpha) * state.lastEyes.rightOpenRatio,
    leftIrisX: alpha * eyes.leftIrisX + (1 - alpha) * state.lastEyes.leftIrisX,
    rightIrisX: alpha * eyes.rightIrisX + (1 - alpha) * state.lastEyes.rightIrisX,
    leftOpen: eyes.leftOpen,
    rightOpen: eyes.rightOpen,
  };

  state.lastEyes = stabilized;
  return stabilized;
}

function computeHeadScoreFromBaseline(pose) {
  const baseline = state.baseline ?? { yaw: 0, pitch: 0, roll: 0 };
  const dyaw = (pose.yaw - baseline.yaw) / 8;
  const dpitch = (pose.pitch - baseline.pitch) / 8;
  const droll = (pose.roll - baseline.roll) / 12;
  return Math.sqrt(dyaw * dyaw + dpitch * dpitch + droll * droll);
}

function computeEyeDeviation(eyes, pose) {
  const baseline = state.baseline;
  if (!baseline || !eyes || !baseline.eyeSamples) {
    return null;
  }

  const yawDelta = pose.yaw - baseline.yaw;
  const screenShift = Math.min(MAX_SCREEN_GAZE_SHIFT, Math.abs(yawDelta) * YAW_TO_SCREEN_GAZE_COMPENSATION);
  const targetLeftX = yawDelta < 0 ? baseline.leftIrisX + screenShift : baseline.leftIrisX - screenShift;
  const targetRightX = yawDelta < 0 ? baseline.rightIrisX + screenShift : baseline.rightIrisX - screenShift;

  const leftShift = Math.abs((eyes.leftIrisX - targetLeftX) / 0.12);
  const rightShift = Math.abs((eyes.rightIrisX - targetRightX) / 0.12);
  const leftOpenShift = Math.abs((eyes.leftOpenRatio - baseline.leftOpenRatio) / 0.08);
  const rightOpenShift = Math.abs((eyes.rightOpenRatio - baseline.rightOpenRatio) / 0.08);

  return {
    left: eyes.leftOpen ? (leftShift * EYE_SHIFT_WEIGHT) + (leftOpenShift * EYE_OPENNESS_WEIGHT) : null,
    right: eyes.rightOpen ? (rightShift * EYE_SHIFT_WEIGHT) + (rightOpenShift * EYE_OPENNESS_WEIGHT) : null,
    leftOpen: eyes.leftOpen,
    rightOpen: eyes.rightOpen,
  };
}

function smoothScore(score) {
  state.smoothedScore = state.smoothedScore == null
    ? score
    : (SCORE_EWMA_ALPHA * score) + ((1 - SCORE_EWMA_ALPHA) * state.smoothedScore);

  return state.smoothedScore;
}

function adaptBaselineTowardCurrent(pose, eyes) {
  if (!state.baseline) return;

  state.baseline.yaw = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.yaw + SELF_CALIBRATION_ALPHA * pose.yaw;
  state.baseline.pitch = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.pitch + SELF_CALIBRATION_ALPHA * pose.pitch;
  state.baseline.roll = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.roll + SELF_CALIBRATION_ALPHA * pose.roll;

  if (eyes && state.baseline.eyeSamples) {
    state.baseline.leftOpenRatio = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.leftOpenRatio + SELF_CALIBRATION_ALPHA * eyes.leftOpenRatio;
    state.baseline.rightOpenRatio = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.rightOpenRatio + SELF_CALIBRATION_ALPHA * eyes.rightOpenRatio;
    state.baseline.leftIrisX = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.leftIrisX + SELF_CALIBRATION_ALPHA * eyes.leftIrisX;
    state.baseline.rightIrisX = (1 - SELF_CALIBRATION_ALPHA) * state.baseline.rightIrisX + SELF_CALIBRATION_ALPHA * eyes.rightIrisX;
  }
}

function selectOverrideEye(eyeDeviation, pose) {
  if (!eyeDeviation) return null;

  if (Math.abs(pose.yaw) >= STRONG_HEAD_TURN_DEGREES) {
    return pose.yaw < 0 ? eyeDeviation.left : eyeDeviation.right;
  }

  if (Math.abs(pose.yaw) >= HEAVY_HEAD_TURN_DEGREES) {
    if (pose.yaw < 0) {
      if (eyeDeviation.left == null) return eyeDeviation.right;
      if (eyeDeviation.right == null) return eyeDeviation.left;
      return Math.min(eyeDeviation.left, eyeDeviation.right + 4);
    }

    if (eyeDeviation.right == null) return eyeDeviation.left;
    if (eyeDeviation.left == null) return eyeDeviation.right;
    return Math.min(eyeDeviation.right, eyeDeviation.left + 4);
  }

  if (eyeDeviation.left == null) return eyeDeviation.right;
  if (eyeDeviation.right == null) return eyeDeviation.left;
  return Math.min(eyeDeviation.left, eyeDeviation.right);
}

function sendState(payload) {
  state.lastStatePayload = payload;
  globalThis.__LOOK_LAST_STATE__ = payload;
  globalThis.__LOOK_INTERNAL_STATE__ = state;
  chrome.runtime.sendMessage({ type: "LOOK_STATE", timestamp: Date.now(), ...payload }).catch(() => {});
}

function emitFatalState(status, error) {
  const message = error?.message || String(error || "unknown_error");
  sendState({
    status,
    mode: "error",
    isLooking: false,
    faceVisible: false,
    baselineReady: false,
    eyeTrackingReady: false,
    score: null,
    headScore: null,
    eyeScore: null,
    leftEyeScore: null,
    rightEyeScore: null,
    awaySeconds: 0,
    awayEventsLocal: state.awayEventsLocal,
    error: message,
  });
}

function emitNoFace(now) {
  if (!state.noFaceSinceMs) {
    state.noFaceSinceMs = now;
  }

  state.stableLookingSinceMs = null;
  if (state.isLooking) {
    state.isLooking = false;
    state.awayStartMs = now;
  }

  const awaySeconds = state.awayStartMs ? (now - state.awayStartMs) / 1000 : 0;

  if (state.awayStartMs && (awaySeconds * 1000) >= AWAY_EVENT_AFTER_MS && !state.lastStatePayload?.awayEventLatched) {
    state.awayEventsLocal += 1;
    chrome.runtime.sendMessage({
      type: "LOOK_AWAY_EVENT",
      id: `${Date.now()}-no-face`,
      occurredAt: new Date().toISOString(),
      score: null,
      awaySeconds: Number(awaySeconds.toFixed(1)),
      reason: "no_face",
    }).catch(() => {});
  }

  sendState({
    status: state.mode === "calibrating" ? "Calibrating - face not found" : "No face detected",
    mode: state.mode === "calibrating" ? "calibrating" : "no_face",
    isLooking: false,
    faceVisible: false,
    baselineReady: Boolean(state.baseline),
    eyeTrackingReady: Boolean(state.baseline?.eyeSamples),
    score: null,
    headScore: null,
    eyeScore: null,
    leftEyeScore: null,
    rightEyeScore: null,
    awaySeconds: Number(awaySeconds.toFixed(1)),
    awayEventsLocal: state.awayEventsLocal,
    awayEventLatched: Boolean(state.lastStatePayload?.awayEventLatched || (state.awayStartMs && (awaySeconds * 1000) >= AWAY_EVENT_AFTER_MS)),
  });
}

function maybeStartDevSimulation(now) {
  if (!DEV_LOCAL_SIMULATION || state.devSimulationActive) {
    return false;
  }

  const cameraReady = video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA;
  if (!cameraReady || !state.noFaceSinceMs) {
    return false;
  }

  if ((now - state.noFaceSinceMs) < DEV_SIMULATION_START_AFTER_NO_FACE_MS) {
    return false;
  }

  state.devSimulationActive = true;
  state.devSimulationStartedAt = now;
  state.lastPose = null;
  state.lastEyes = null;
  state.lastStatePayload = null;
  state.awayStartMs = null;
  state.stableLookingSinceMs = now;

  sendState({
    status: "Dev simulation active",
    mode: "simulation",
    isLooking: true,
    faceVisible: true,
    baselineReady: false,
    eyeTrackingReady: false,
    score: null,
    headScore: null,
    eyeScore: null,
    leftEyeScore: null,
    rightEyeScore: null,
    awaySeconds: 0,
    awayEventsLocal: state.awayEventsLocal,
  });

  return true;
}

function armDevSimulationFallback() {
  if (!DEV_LOCAL_SIMULATION || devSimulationTimer) {
    return;
  }

  devSimulationTimer = setTimeout(() => {
    devSimulationTimer = null;

    if (state.devSimulationActive || state.lastStatePayload?.faceVisible) {
      return;
    }

    state.devSimulationActive = true;
    state.devSimulationStartedAt = Date.now();
    state.lastPose = null;
    state.lastEyes = null;
    state.lastStatePayload = null;
    state.awayStartMs = null;
    beginCalibration(Date.now(), { preserveSimulation: true });
  }, DEV_SIMULATION_START_AFTER_NO_FACE_MS);
}

function getSimulatedFrame(now) {
  if (!state.devSimulationStartedAt) {
    state.devSimulationStartedAt = now;
  }

  const elapsed = now - state.devSimulationStartedAt;
  if (elapsed < CALIBRATION_MS + 1000) {
    return {
      pose: DEV_LOOKING_POSE,
      eyes: DEV_EYES,
    };
  }

  const cycleOffset = (elapsed - CALIBRATION_MS - 1000) % DEV_SIMULATION_CYCLE_MS;
  const awayPhase = cycleOffset >= 3500 && cycleOffset < 7000;

  return {
    pose: awayPhase ? DEV_AWAY_POSE : DEV_LOOKING_POSE,
    eyes: DEV_EYES,
  };
}

function completeCalibration() {
  state.baseline = averageCalibration(state.calibrationSamples);
  state.mode = "tracking";
  state.smoothedScore = null;
  state.awayStartMs = null;
  state.isLooking = true;
  state.stableLookingSinceMs = Date.now();
}

function maybeSelfCalibrate(now, score, eyes, pose) {
  if (!(state.isLooking && score <= SELF_CALIBRATION_MIN_SCORE)) {
    state.stableLookingSinceMs = null;
    return;
  }

  if (!state.stableLookingSinceMs) {
    state.stableLookingSinceMs = now;
    return;
  }

  if ((now - state.stableLookingSinceMs) >= SELF_CALIBRATION_MIN_STABLE_MS) {
    adaptBaselineTowardCurrent(pose, eyes);
  }
}

function handleTracking(now, pose, eyes) {
  const stabilizedPose = clampPoseJump(pose);
  const stabilizedEyes = smoothEyes(eyes);
  const headScore = computeHeadScoreFromBaseline(stabilizedPose);
  const eyeDeviation = computeEyeDeviation(stabilizedEyes, stabilizedPose);
  const effectiveEyeScore = selectOverrideEye(eyeDeviation, stabilizedPose);

  let combinedScore = headScore;
  if (effectiveEyeScore != null && effectiveEyeScore < EYE_OVERRIDE_MAX) {
    combinedScore = Math.min(combinedScore, EXIT_AWAY_THRESHOLD - 0.1);
  } else if (effectiveEyeScore != null && effectiveEyeScore >= EYE_AWAY_THRESHOLD) {
    combinedScore = Math.max(combinedScore, ENTER_AWAY_THRESHOLD + 0.1);
  }

  const score = smoothScore(combinedScore);

  if (state.isLooking) {
    if (score >= ENTER_AWAY_THRESHOLD) {
      state.isLooking = false;
      state.awayStartMs = now;
      state.stableLookingSinceMs = null;
    }
  } else if (score <= EXIT_AWAY_THRESHOLD) {
    state.isLooking = true;
    state.awayStartMs = null;
    state.stableLookingSinceMs = now;
  }

  const awaySeconds = state.awayStartMs ? (now - state.awayStartMs) / 1000 : 0;

  if (!state.isLooking && state.awayStartMs && (awaySeconds * 1000) >= AWAY_EVENT_AFTER_MS && !state.lastStatePayload?.awayEventLatched) {
    state.awayEventsLocal += 1;
    chrome.runtime.sendMessage({
      type: "LOOK_AWAY_EVENT",
      id: `${Date.now()}-look-away`,
      occurredAt: new Date().toISOString(),
      score: Number(score.toFixed(2)),
      awaySeconds: Number(awaySeconds.toFixed(1)),
    }).catch(() => {});

    sendState({
      status: "Attention lost",
      mode: "away",
      isLooking: false,
      faceVisible: true,
      baselineReady: true,
      eyeTrackingReady: Boolean(state.baseline?.eyeSamples),
      score: Number(score.toFixed(2)),
      headScore: Number(headScore.toFixed(2)),
      eyeScore: effectiveEyeScore == null ? null : Number(effectiveEyeScore.toFixed(2)),
      leftEyeScore: eyeDeviation?.left == null ? null : Number(eyeDeviation.left.toFixed(2)),
      rightEyeScore: eyeDeviation?.right == null ? null : Number(eyeDeviation.right.toFixed(2)),
      leftEyeConfidence: eyeDeviation?.leftOpen ?? null,
      rightEyeConfidence: eyeDeviation?.rightOpen ?? null,
      awaySeconds: Number(awaySeconds.toFixed(1)),
      awayEventsLocal: state.awayEventsLocal,
      awayEventLatched: true,
    });
    return;
  }

  maybeSelfCalibrate(now, score, stabilizedEyes, stabilizedPose);

  sendState({
    status: state.isLooking ? "Looking at screen" : "Looking away",
    mode: state.isLooking ? "tracking" : "away",
    isLooking: state.isLooking,
    faceVisible: true,
    baselineReady: true,
    eyeTrackingReady: Boolean(state.baseline?.eyeSamples),
    score: Number(score.toFixed(2)),
    headScore: Number(headScore.toFixed(2)),
    eyeScore: effectiveEyeScore == null ? null : Number(effectiveEyeScore.toFixed(2)),
    leftEyeScore: eyeDeviation?.left == null ? null : Number(eyeDeviation.left.toFixed(2)),
    rightEyeScore: eyeDeviation?.right == null ? null : Number(eyeDeviation.right.toFixed(2)),
    leftEyeConfidence: eyeDeviation?.leftOpen ?? null,
    rightEyeConfidence: eyeDeviation?.rightOpen ?? null,
    awaySeconds: Number(awaySeconds.toFixed(1)),
    awayEventsLocal: state.awayEventsLocal,
    awayEventLatched: Boolean(state.lastStatePayload?.awayEventLatched && !state.isLooking),
  });
}

function handleCalibration(now, pose, eyes) {
  state.calibrationSamples.push({
    pose,
    eyes,
  });

  const elapsedMs = now - state.calibrationStartedAt;

  sendState({
    status: `Calibrating ${Math.min(100, Math.round((elapsedMs / CALIBRATION_MS) * 100))}%`,
    mode: "calibrating",
    isLooking: true,
    faceVisible: true,
    baselineReady: false,
    eyeTrackingReady: false,
    score: null,
    headScore: null,
    eyeScore: null,
    leftEyeScore: null,
    rightEyeScore: null,
    awaySeconds: 0,
    awayEventsLocal: state.awayEventsLocal,
  });

  if (elapsedMs >= CALIBRATION_MS && state.calibrationSamples.length >= 8) {
    completeCalibration();
  }
}

function scheduleNextLoop() {
  clearTimeout(loopTimer);
  loopTimer = setTimeout(loop, SAMPLE_INTERVAL_MS);
}

function stopLoop() {
  clearTimeout(loopTimer);
  loopTimer = null;
}

function scheduleFullRestart(reason) {
  if (restartTimer) {
    return;
  }

  stopLoop();
  emitFatalState(`Restarting attention tracker: ${reason}`, reason);
  restartTimer = setTimeout(() => {
    restartTimer = null;
    bootstrapTracking(reason).catch((error) => {
      console.error("Attention tracker restart failed:", error);
      scheduleFullRestart("restart failed");
    });
  }, BOOTSTRAP_RETRY_MS);
}

function loop() {
  if (!faceLandmarker) return;

  const now = Date.now();
  if (state.lastLoopAt && (now - state.lastLoopAt) > RESUME_GAP_MS) {
    state.lastLoopAt = now;
    scheduleFullRestart("sleep or resume detected");
    return;
  }
  state.lastLoopAt = now;

  let pose = null;
  let eyes = null;

  try {
    if (video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
      throw new Error("camera video is not producing frames");
    }
    const result = faceLandmarker.detectForVideo(video, performance.now());
    const mats = result?.facialTransformationMatrixes;
    if (mats?.length > 0) {
      pose = toDegreesPose(mats[0].data);
    }
    eyes = computeEyeMetrics(result?.faceLandmarks?.[0] ?? null);
  } catch (error) {
    emitNoFace(now);
    scheduleNextLoop();
    return;
  }

  if (!pose) {
    if (state.devSimulationActive || maybeStartDevSimulation(now)) {
      const simulated = getSimulatedFrame(now);
      pose = simulated.pose;
      eyes = simulated.eyes;
    } else {
      emitNoFace(now);
      scheduleNextLoop();
      return;
    }
  } else {
    state.noFaceSinceMs = null;
  }

  if (state.mode === "starting") {
    beginCalibration(now, { preserveSimulation: state.devSimulationActive });
  }

  if (state.mode === "calibrating") {
    handleCalibration(now, pose, eyes);
  } else {
    handleTracking(now, pose, eyes);
  }

  scheduleNextLoop();
}

chrome.runtime.onMessage.addListener((msg) => {
  if (msg?.type === "RECALIBRATE_OFFSCREEN") {
    beginCalibration();
  }
});

async function bootstrapTracking(reason = null) {
  if (bootstrapping) {
    return;
  }
  bootstrapping = true;
  stopLoop();
  clearTimeout(devSimulationTimer);
  devSimulationTimer = null;

  try {
    sendState({
      status: reason ? `Starting attention tracker after ${reason}` : "Starting attention tracker",
      mode: "starting",
      isLooking: true,
      faceVisible: false,
      baselineReady: false,
      eyeTrackingReady: false,
      score: null,
      headScore: null,
      eyeScore: null,
      leftEyeScore: null,
      rightEyeScore: null,
      awaySeconds: 0,
      awayEventsLocal: state.awayEventsLocal,
    });
    installDeviceChangeListener();
    await startCamera();
  } catch (error) {
    emitFatalState("Camera initialization failed", error);
    scheduleFullRestart("camera initialization failed");
    bootstrapping = false;
    return;
  }

  try {
    await loadModel();
  } catch (error) {
    emitFatalState("Face model failed to load", error);
    scheduleFullRestart("face model failed to load");
    bootstrapping = false;
    return;
  }

  beginCalibration();
  state.lastLoopAt = Date.now();
  armDevSimulationFallback();
  bootstrapping = false;
  loop();
}

bootstrapTracking().catch((error) => {
  console.error("Offscreen tracking bootstrap failed:", error);
  scheduleFullRestart("bootstrap failed");
});
