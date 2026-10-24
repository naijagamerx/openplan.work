"""Deploy the APK-download fix over FTP.

Sources creds from CLAUDE.local.md (gitignored: host/user/password);
no secret on the command line. Web root = FTP cwd (/public_html).

Uploads (relative to web root), normalizing CRLF -> LF:
  1. .htaccess                                   (overwrite)
  2. release-artifacts/android/.htaccess         (create)
  3. release-artifacts/.htaccess                 (overwrite, comment update)
Each is verified by fetching it back and comparing bytes.
"""
import ftplib
import io
import os
import re

ROOT = r"C:\MAMP\htdocs\taskmanager"

with open(os.path.join(ROOT, "CLAUDE.local.md"), encoding="utf-8") as f:
    claude = f.read()
# Host: first IPv4 on the "FTP IP" line (format changed over time — be IPv4-aware).
_ftp_ip_line = next(ln for ln in claude.splitlines() if "FTP IP" in ln)
host = re.search(r"\d{1,3}(?:\.\d{1,3}){3}", _ftp_ip_line).group(0)
user = re.search(r"Username\s*\|\s*`([^`]+)`", claude).group(1)
# Password: first backtick-quoted value on the "| Password" line (stored in CLAUDE.local.md).
_pwd_line = next(ln for ln in claude.splitlines() if ln.lstrip().startswith("| Password"))
password = re.search(r"\|\s*`([^`]+)`", _pwd_line).group(1)

# (local path, remote path)
TARGETS = [
    (os.path.join(ROOT, ".htaccess"),
     ".htaccess"),
    (os.path.join(ROOT, "release-artifacts", "android", ".htaccess"),
     "release-artifacts/android/.htaccess"),
    (os.path.join(ROOT, "release-artifacts", ".htaccess"),
     "release-artifacts/.htaccess"),
]

ftp = ftplib.FTP(host, timeout=60)
ftp.login(user, password)
print("LOGIN OK; pwd=", ftp.pwd())


def put(remote_path, data_bytes):
    ftp.storbinary(f"STOR {remote_path}", io.BytesIO(data_bytes))


def get(remote_path):
    buf = io.BytesIO()
    ftp.retrbinary(f"RETR {remote_path}", buf.write)
    return buf.getvalue()


all_ok = True
for local_path, remote_path in TARGETS:
    with open(local_path, "rb") as f:
        raw = f.read()
    data = raw.replace(b"\r\n", b"\n")  # normalize to LF for Apache/Linux
    put(remote_path, data)
    back = get(remote_path)
    ok = back == data
    all_ok = all_ok and ok
    print(f"[{'OK' if ok else 'FAIL'}] {remote_path}  "
          f"(uploaded {len(data)}B, local raw {len(raw)}B)")

# Sanity: confirm android .htaccess now lists.
print("\n== release-artifacts/android/ LIST after deploy ==")
items = []
ftp.cwd("release-artifacts/android")
ftp.retrlines("LIST", items.append)
for l in items:
    print(l)

ftp.quit()
print("\nALL UPLOADS VERIFIED:", all_ok)
