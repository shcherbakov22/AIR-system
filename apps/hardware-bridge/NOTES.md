# Hardware Bridge Notes

Operational notes for connected hardware such as the ESP32 pushup counter.

## Recent Changes And Context

- Pushup counter work moved toward a standalone device that talks directly to the server.
- Hardware uses ultrasonic distance sensing and buzzer feedback.
- Remote status, logs, restart, and session-ending controls are planned/needed for reliable operation.

## Gotchas

- Sensor thresholds vary by student body proportions and position.
- Fast pushups and forward/back body shifts can break simple distance thresholds.
- Device sessions should drop when the corresponding violation is removed.
- Remote debugging/logging is important because physical access to the hardware is inconvenient.
