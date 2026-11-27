# Put a Gmail app password into .env without editing the file by hand, then send a test e-mail.
#   pwsh bin/set_mail_password.ps1
# What you type is hidden and is never printed.
param(
    [securestring]$Password,
    [string]$EnvPath = (Join-Path (Split-Path $PSScriptRoot -Parent) '.env')
)
$ErrorActionPreference = 'Stop'

if (-not (Test-Path $EnvPath)) { throw '.env was not found.' }
if (-not $Password) { $Password = Read-Host 'Paste the 16-letter Google app password' -AsSecureString }

$plain = [System.Net.NetworkCredential]::new('', $Password).Password -replace '\s', ''
if ($plain -cnotmatch '^[a-zA-Z]{16}$') {
    Write-Host "Not changed: an app password has exactly 16 letters, but $($plain.Length) characters were given." -ForegroundColor Red
    Write-Host 'Create one at https://myaccount.google.com/apppasswords (it is not your Gmail password).'
    exit 1
}

$text = [System.IO.File]::ReadAllText($EnvPath)
$line = "MAIL_PASS=$($plain.ToLowerInvariant())"
if ($text -match '(?m)^MAIL_PASS=') {
    $text = [regex]::Replace($text, '(?m)^MAIL_PASS=[^\r\n]*', $line)
} else {
    $text = $text.TrimEnd() + "`n$line`n"
}
[System.IO.File]::WriteAllText($EnvPath, $text)
Write-Host 'Saved in .env.' -ForegroundColor Green

if ($PSBoundParameters.ContainsKey('EnvPath')) { exit 0 }

$user = [regex]::Match($text, '(?m)^MAIL_USER=["'']?([^"''\r\n]+)').Groups[1].Value
if ($user) {
    Push-Location (Split-Path $PSScriptRoot -Parent)
    try { docker compose exec -T web php bin/test_mail.php $user } finally { Pop-Location }
} else {
    Write-Host 'MAIL_USER is empty in .env: add your Gmail address there.'
}
