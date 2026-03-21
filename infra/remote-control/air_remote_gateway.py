#!/usr/bin/env python3
import json
import socket
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import quote, urlparse

STATE_DIR = Path("/var/lib/air-remote-control")
SESSION_DIR = STATE_DIR / "sessions"
TOKEN_FILE = STATE_DIR / "tokens.txt"
NOVNC_DIR = Path("/usr/share/novnc")
LISTEN_HOST = "127.0.0.1"
LISTEN_PORT = 9821


def ensure_dirs():
    SESSION_DIR.mkdir(parents=True, exist_ok=True)
    TOKEN_FILE.parent.mkdir(parents=True, exist_ok=True)
    TOKEN_FILE.touch(exist_ok=True)


def session_path(session_token: str) -> Path:
    return SESSION_DIR / f"{session_token}.json"


def load_session(session_token: str):
    path = session_path(session_token)
    if not path.exists():
        return None
    return json.loads(path.read_text(encoding="utf-8"))


def save_session(data: dict):
    session_path(data["session_token"]).write_text(json.dumps(data), encoding="utf-8")


def update_token_file():
    lines = []
    for path in SESSION_DIR.glob("*.json"):
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            continue

        if data.get("status") != "active":
            continue

        target_host = data.get("target_host")
        target_port = data.get("target_port")
        session_token = data.get("session_token")
        if not target_host or not target_port or not session_token:
            continue

        lines.append(f"{session_token}: {target_host}:{target_port}")
    TOKEN_FILE.write_text("\n".join(lines) + ("\n" if lines else ""), encoding="utf-8")


def reachable(host: str, port: int, timeout: float = 2.0) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.settimeout(timeout)
        try:
            sock.connect((host, port))
        except OSError:
            return False
    return True


def stop_session(session_token: str):
    data = load_session(session_token)
    if not data:
        return False
    data["status"] = "ended"
    data["ended_at"] = int(time.time())
    save_session(data)
    update_token_file()
    return True


def start_session(payload: dict):
    session_token = payload["session_token"]
    target_host = payload["target_host"]
    target_port = int(payload["target_port"])

    if target_port <= 0 or target_port > 65535:
        raise RuntimeError("invalid target_port")

    if not reachable(target_host, target_port):
        raise RuntimeError("companion VNC endpoint is unreachable")

    save_session(
        {
            "session_token": session_token,
            "status": "active",
            "target_host": target_host,
            "target_port": target_port,
            "label": payload.get("label", ""),
            "started_at": int(time.time()),
        }
    )
    update_token_file()
    return {
        "session_id": session_token,
        "viewer_path": f"/remote-control/view/{session_token}",
    }


class Handler(BaseHTTPRequestHandler):
    def _send_json(self, status: int, payload: dict):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _body_json(self):
        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length) if length > 0 else b"{}"
        return json.loads(raw.decode("utf-8"))

    def do_POST(self):
        parsed = urlparse(self.path)
        if parsed.path == "/api/sessions/start":
            try:
                payload = self._body_json()
                for key in ("session_token", "target_host", "target_port"):
                    if not payload.get(key):
                        raise RuntimeError(f"missing {key}")
                result = start_session(payload)
                self._send_json(HTTPStatus.OK, result)
            except Exception as exc:
                self._send_json(HTTPStatus.BAD_REQUEST, {"error": str(exc)})
            return

        if parsed.path == "/api/sessions/stop":
            payload = self._body_json()
            if stop_session(payload.get("session_token", "")):
                self._send_json(HTTPStatus.OK, {"stopped": True})
            else:
                self._send_json(HTTPStatus.NOT_FOUND, {"stopped": False})
            return

        self.send_error(HTTPStatus.NOT_FOUND)

    def do_GET(self):
        parsed = urlparse(self.path)
        if parsed.path.startswith("/remote-control/view/"):
            session_token = parsed.path.rsplit("/", 1)[-1]
            if not load_session(session_token):
                self.send_error(HTTPStatus.NOT_FOUND)
                return
            query = quote(f"remote-control/ws?token={session_token}", safe="/?=&")
            location = (
                f"/remote-control/static/vnc_lite.html"
                f"?autoconnect=1&resize=scale&view_clip=0&reconnect=1&path={query}"
            )
            self.send_response(HTTPStatus.FOUND)
            self.send_header("Location", location)
            self.end_headers()
            return

        if parsed.path.startswith("/remote-control/static/"):
            relative = parsed.path.removeprefix("/remote-control/static/")
            full = (NOVNC_DIR / relative).resolve()
            if not str(full).startswith(str(NOVNC_DIR.resolve())) or not full.exists() or not full.is_file():
                self.send_error(HTTPStatus.NOT_FOUND)
                return

            content = full.read_bytes()
            if full.suffix == ".html":
                content_type = "text/html; charset=utf-8"
            elif full.suffix == ".js":
                content_type = "application/javascript"
            elif full.suffix == ".css":
                content_type = "text/css"
            elif full.suffix == ".svg":
                content_type = "image/svg+xml"
            elif full.suffix == ".png":
                content_type = "image/png"
            else:
                content_type = "application/octet-stream"

            self.send_response(HTTPStatus.OK)
            self.send_header("Content-Type", content_type)
            self.send_header("Content-Length", str(len(content)))
            self.end_headers()
            self.wfile.write(content)
            return

        self.send_error(HTTPStatus.NOT_FOUND)


def main():
    ensure_dirs()
    server = ThreadingHTTPServer((LISTEN_HOST, LISTEN_PORT), Handler)
    server.serve_forever()


if __name__ == "__main__":
    main()
