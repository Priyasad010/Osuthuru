# Point APP_URL (used in QR codes and email links) at this PC's current network address,
# so that a phone on the same network can open them.
#
#   pwsh bin/set_lan_url.ps1          use the network address
#   pwsh bin/set_lan_url.ps1 -Local   go back to http://localhost:8080
param([switch]$Local, [int]$Port = 8080)

$ErrorActionPreference = 'Stop'
$envFile = Join-Path (Split-Path $PSScriptRoot -Parent) '.env'
if (-not (Test-Path $envFile)) { throw ".env not found. Copy .env.example to .env first." }

if ($Local) {
    $address = 'localhost'
} else {
    $config = Get-NetIPConfiguration |
        Where-Object { $_.IPv4DefaultGateway -and $_.NetAdapter.Status -eq 'Up' } |
        Select-Object -First 1
    if (-not $config) { throw 'No active network connection found.' }
    $address = @($config.IPv4Address.IPAddress)[0]
}
$url = "http://${address}:$Port"

$text = [System.IO.File]::ReadAllText($envFile)
if ($text -match '(?m)^APP_URL=') {
    $text = [regex]::Replace($text, '(?m)^APP_URL=[^\r\n]*', "APP_URL=$url")
} else {
    $text = $text.TrimEnd() + "`nAPP_URL=$url`n"
}
[System.IO.File]::WriteAllText($envFile, $text)

Write-Host "APP_URL is now $url"
if (-not $Local) {
    Write-Host "Open $url on a phone connected to the same network."
    Write-Host "Run this again if the PC changes network, and print new QR labels."
}
