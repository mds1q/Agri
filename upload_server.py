from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse
import html
import re
from email import policy
from email.parser import BytesParser

ROOT = Path(__file__).resolve().parent
UPLOADS = ROOT / "uploads"
MAX_BYTES = 12 * 1024 * 1024
ALLOWED = {"image/png": ".png", "image/jpeg": ".jpg", "image/webp": ".webp"}


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def do_POST(self):
        if urlparse(self.path).path != "/upload":
            self.send_error(404)
            return
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            self.send_error(400, "Invalid content length")
            return
        if length <= 0 or length > MAX_BYTES + 1024 * 1024:
            self.send_error(413, "Upload must be under 12 MB")
            return
        content_type = self.headers.get("Content-Type", "")
        if not content_type.lower().startswith("multipart/form-data;"):
            self.send_error(400, "Expected multipart form data")
            return
        message = BytesParser(policy=policy.default).parsebytes(
            f"Content-Type: {content_type}\r\nMIME-Version: 1.0\r\n\r\n".encode() + self.rfile.read(length)
        )
        item = next((part for part in message.iter_parts() if part.get_content_disposition() == "form-data" and part.get_param("name", header="content-disposition") == "image"), None)
        if item is None:
            self.send_error(400, "Choose an image")
            return
        mime = item.get_content_type().lower()
        extension = ALLOWED.get(mime)
        if not extension:
            self.send_error(415, "Only PNG, JPG, and WebP images are accepted")
            return
        data = item.get_payload(decode=True) or b""
        if len(data) > MAX_BYTES:
            self.send_error(413, "Image must be under 12 MB")
            return
        signatures = {
            "image/png": data.startswith(b"\x89PNG\r\n\x1a\n"),
            "image/jpeg": data.startswith(b"\xff\xd8\xff"),
            "image/webp": len(data) >= 12 and data[:4] == b"RIFF" and data[8:12] == b"WEBP",
        }
        if not signatures[mime]:
            self.send_error(415, "File content does not match its image type")
            return
        name = Path(item.get_filename() or "icon").stem
        name = re.sub(r"[^A-Za-z0-9_-]+", "-", name).strip("-_")[:60] or "icon"
        UPLOADS.mkdir(exist_ok=True)
        target = UPLOADS / f"{name}{extension}"
        suffix = 2
        while target.exists():
            target = UPLOADS / f"{name}-{suffix}{extension}"
            suffix += 1
        target.write_bytes(data)
        result = f'''<!doctype html><html lang="bn"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>আপলোড সম্পন্ন</title><body style="font:16px system-ui;max-width:600px;margin:48px auto;padding:20px;color:#10233f"><h1>ছবি আপলোড হয়েছে</h1><p>ফাইল: <a href="/{html.escape(target.relative_to(ROOT).as_posix())}">{html.escape(target.name)}</a></p><p><a href="/upload.html">আরেকটি ছবি আপলোড করুন</a></p></body></html>'''
        body = result.encode("utf-8")
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)


if __name__ == "__main__":
    print("Upload page: http://localhost:8000/upload.html", flush=True)
    ThreadingHTTPServer(("0.0.0.0", 8000), Handler).serve_forever()
