#!/usr/bin/env bash
set -euo pipefail

CACHE_DIR="${1:-/tmp/ps-emailsub-apk}"
MIRROR="${2:-https://mirror.leaseweb.net/alpine/v3.10}"
mkdir -p "$CACHE_DIR"

curl -fsSL "$MIRROR/main/x86_64/APKINDEX.tar.gz" -o "$CACHE_DIR/APKINDEX.main.tar.gz"
curl -fsSL "$MIRROR/community/x86_64/APKINDEX.tar.gz" -o "$CACHE_DIR/APKINDEX.community.tar.gz"

python3 - <<'PY' "$CACHE_DIR" "$MIRROR"
import os, re, sys, tarfile, urllib.request

cache, mirror = sys.argv[1], sys.argv[2]
wanted_roots = {
    "gnupg", "curl", "coreutils", "dpkg", "dpkg-dev", "file", "g++", "gcc", "libc-dev",
    "make", "pkgconf", "re2c", "autoconf", "mariadb-dev", "mariadb-connector-c-dev", "python3",
}
packages = {}
providers = {}

def load_index(path):
    with tarfile.open(path, "r:gz") as tf:
        data = tf.extractfile("APKINDEX").read().decode("utf-8", "ignore")
    for block in data.strip().split("\n\n"):
        fields = {}
        deps = []
        prov = []
        for line in block.splitlines():
            if line.startswith("P:"):
                fields["P"] = line[2:]
            elif line.startswith("V:"):
                fields["V"] = line[2:]
            elif line.startswith("A:"):
                fields["A"] = line[2:]
            elif line.startswith("D:"):
                deps = line[2:].split()
            elif line.startswith("p:"):
                prov.append(line[2:])
        if fields.get("A") == "x86_64" and "P" in fields and "V" in fields:
            packages[fields["P"]] = {"version": fields["V"], "deps": deps}
            for item in prov:
                if item.startswith("so:"):
                    providers.setdefault(item.split("=", 1)[0], fields["P"])

for name in ("APKINDEX.main.tar.gz", "APKINDEX.community.tar.gz"):
    load_index(os.path.join(cache, name))

def resolve_dep(dep):
    if dep.startswith("so:"):
        return providers.get(dep)
    return re.split(r"[<>=]", dep)[0]

selected = set()
queue = list(wanted_roots)
while queue:
    pkg = queue.pop()
    if pkg in selected or pkg not in packages:
        continue
    selected.add(pkg)
    for dep in packages[pkg]["deps"]:
        resolved = resolve_dep(dep)
        if resolved and resolved not in selected:
            queue.append(resolved)

# Second pass for shared-library dependencies.
changed = True
while changed:
    changed = False
    for pkg in list(selected):
        for dep in packages[pkg]["deps"]:
            if not dep.startswith("so:"):
                continue
            resolved = providers.get(dep)
            if resolved and resolved not in selected:
                selected.add(resolved)
                changed = True
                queue.append(resolved)
    while queue:
        pkg = queue.pop()
        if pkg not in packages or pkg in selected:
            continue
        selected.add(pkg)
        changed = True
        for dep in packages[pkg]["deps"]:
            resolved = resolve_dep(dep)
            if resolved and resolved not in selected:
                queue.append(resolved)
            if dep.startswith("so:"):
                resolved_so = providers.get(dep)
                if resolved_so and resolved_so not in selected:
                    queue.append(resolved_so)

for pkg in sorted(selected):
    info = packages[pkg]
    filename = f"{pkg}-{info['version']}.apk"
    dest = os.path.join(cache, filename)
    if os.path.exists(dest):
        continue
    for repo in ("main", "community"):
        url = f"{mirror}/{repo}/x86_64/{filename}"
        try:
            with urllib.request.urlopen(url) as resp, open(dest, "wb") as out:
                out.write(resp.read())
            print("downloaded", filename)
            break
        except Exception:
            continue
    else:
        raise SystemExit(f"failed to download {filename}")

print("selected", len(selected), "packages")
PY

bash "$(dirname "$0")/verify-apk-cache.sh" "$CACHE_DIR" "$MIRROR"
