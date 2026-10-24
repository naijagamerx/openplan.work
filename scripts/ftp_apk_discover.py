"""Read-only discovery for the APK 403 deploy.

Sources creds from CLAUDE.local.md (gitignored: host/user) and test_ftp_diagnostic.py (password)
so the secret never goes on the command line. No uploads. Prints:
  - root listing (orientation)
  - live root .htaccess
  - live release-artifacts/.htaccess
  - live release-artifacts/android/.htaccess
  - release-artifacts/android/ listing (to find the APK filename)
"""
import ftplib
import io
import os
import re

ROOT = r"C:\MAMP\htdocs\taskmanager"

with open(os.path.join(ROOT, "CLAUDE.local.md"), encoding="utf-8") as f:
    claude = f.read()
# Host: first IPv4 on the "FTP IP" line (the line is "FTP IP (hostname) | `<ip>` ...").
_ftp_ip_line = next(ln for ln in claude.splitlines() if "FTP IP" in ln)
host = re.search(r"\d{1,3}(?:\.\d{1,3}){3}", _ftp_ip_line).group(0)
user = re.search(r"Username\s*\|\s*`([^`]+)`", claude).group(1)

with open(os.path.join(ROOT, "test_ftp_diagnostic.py"), encoding="utf-8") as f:
    diag = f.read()
password = re.search(r'password\s*=\s*"([^"]+)"', diag).group(1)

print(f"host={host}  user={user}")

ftp = ftplib.FTP(host, timeout=30)
ftp.login(user, password)
print("LOGIN OK; pwd=", ftp.pwd())


def fetch(path):
    buf = io.BytesIO()
    try:
        ftp.retrbinary(f"RETR {path}", buf.write)
        return buf.getvalue().decode("utf-8", "replace")
    except Exception as e:
        return f"<<ERR: {e}>>"


def listing(path):
    items = []
    try:
        ftp.cwd(path)
        ftp.retrlines("LIST", items.append)
    except Exception as e:
        return [f"<<ERR: {e}>>"]
    return items


print("\n== ROOT LIST (orientation) ==")
for l in listing(".")[:40]:
    print(l)

print("\n== LIVE ROOT .htaccess ==")
print(fetch(".htaccess"))

print("\n== LIVE release-artifacts/.htaccess ==")
print(fetch("release-artifacts/.htaccess"))

print("\n== LIVE release-artifacts/android/.htaccess ==")
print(fetch("release-artifacts/android/.htaccess"))

print("\n== release-artifacts/android/ LIST ==")
for l in listing("release-artifacts/android"):
    print(l)

ftp.quit()
print("\nDONE")
