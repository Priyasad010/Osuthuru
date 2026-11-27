# HTTPS on the local network, so that a phone (including an iPhone) can install
# the app and use it offline.
#
#   pwsh bin/setup_https.ps1           create a certificate for this PC's address
#   pwsh bin/setup_https.ps1 -Remove   turn HTTPS off again
#
# A one-off certificate authority signs the server certificate and its private
# key is deleted immediately, so trusting it on a phone cannot be used to
# impersonate any other site.
param([switch]$Remove)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root
$certs = Join-Path $root 'docker/certs'
$publicCa = Join-Path $root 'public/lan-ca.crt'

function Invoke-Compose {
    docker compose @args
    if ($LASTEXITCODE -ne 0) { throw "docker compose $($args -join ' ') failed. Is Docker Desktop running?" }
}

if ($Remove) {
    Remove-Item $certs -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $publicCa -Force -ErrorAction SilentlyContinue
    Invoke-Compose restart web
    Write-Host 'HTTPS is off. Also delete the "Osuthuru local" profile on the phone.'
    return
}

$config = Get-NetIPConfiguration |
    Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
    Select-Object -First 1
if (-not $config) { throw 'No active network connection found.' }
$ip = @($config.IPv4Address.IPAddress)[0]

# Starts the container with the HTTPS port and configuration
Invoke-Compose up -d web

$script = @'
set -eu
cd /var/www/html
mkdir -p docker/certs
cd docker/certs
umask 077
openssl req -x509 -newkey rsa:2048 -nodes -sha256 -days 397 -keyout ca.key -out ca.crt \
  -subj "/CN=Osuthuru local __IP__" \
  -addext "basicConstraints=critical,CA:TRUE,pathlen:0" -addext "keyUsage=critical,keyCertSign,cRLSign" 2>/dev/null
openssl req -newkey rsa:2048 -nodes -sha256 -keyout server.key -out server.csr -subj "/CN=__IP__" 2>/dev/null
printf '%s\n' "basicConstraints=CA:FALSE" "keyUsage=critical,digitalSignature,keyEncipherment" \
  "extendedKeyUsage=serverAuth" "subjectAltName=IP:__IP__,IP:127.0.0.1,DNS:localhost" > server.ext
openssl x509 -req -in server.csr -CA ca.crt -CAkey ca.key -CAcreateserial -sha256 -days 397 \
  -extfile server.ext -out server.crt 2>/dev/null
openssl verify -CAfile ca.crt server.crt
rm -f ca.key ca.srl server.csr server.ext
cp ca.crt ../../public/lan-ca.crt
chmod 644 ca.crt server.crt ../../public/lan-ca.crt
'@
# PowerShell ends piped text with a Windows line break; the closing "#" turns
# that stray carriage return into a comment.
$script = $script.Replace('__IP__', $ip).Replace("`r", '') + "`n#"
$script | docker compose exec -T web sh
if ($LASTEXITCODE -ne 0) { throw 'The certificate could not be created.' }

Invoke-Compose restart web

Write-Host ''
Write-Host "HTTPS is ready: https://${ip}:8443"
Write-Host ''
Write-Host 'On the iPhone (same Wi-Fi), once:'
Write-Host "  1. Safari: open http://${ip}:8080/lan-ca.crt and allow the download."
Write-Host '  2. Settings > General > VPN & Device Management > install the "Osuthuru local" profile.'
Write-Host '  3. Settings > General > About > Certificate Trust Settings > switch "Osuthuru local" on.'
Write-Host "  4. Safari: open https://${ip}:8443 , then Share > Add to Home Screen."
Write-Host 'Run this again if the PC changes network.'
