# Stellt die Anwendung öffentlich bereit: https://curse-mulberry-rule.ngrok-free.dev/
# Startet zuerst MySQL (XAMPP) und die Anwendung, danach den ngrok-Tunnel.
# Durch den Tunnel ist nur die Anwendung erreichbar, nicht phpMyAdmin oder die Datenbank.
# Beenden mit Strg+C.

$ErrorActionPreference = 'Stop'
$adresse = 'curse-mulberry-rule.ngrok-free.dev'

& (Join-Path $PSScriptRoot 'xampp-start.ps1')

if (-not (Get-Command ngrok -ErrorAction SilentlyContinue)) {
    throw 'ngrok fehlt: winget install ngrok.ngrok, danach einmalig ngrok config add-authtoken <eigener Schluessel>'
}

Write-Host ''
Write-Host "Oeffentlich erreichbar: https://$adresse/   (Beenden mit Strg+C)" -ForegroundColor Green
# 127.0.0.1 statt localhost, damit ngrok nicht über IPv6 (::1) ins Leere verbindet
ngrok http 127.0.0.1:8000 --url "https://$adresse"
