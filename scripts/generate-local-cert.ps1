Param(
    [string]$CertDir = "certs"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command mkcert -ErrorAction SilentlyContinue)) {
    Write-Host "mkcert n'est pas installe."
    Write-Host "Installe-le puis relance ce script:"
    Write-Host "  winget install FiloSottile.mkcert"
    exit 1
}

if (-not (Test-Path $CertDir)) {
    New-Item -ItemType Directory -Path $CertDir | Out-Null
}

Push-Location $CertDir
try {
    mkcert -install
    mkcert -cert-file server.crt -key-file server.key localhost 127.0.0.1 ::1
    Write-Host "Certificat local genere dans $CertDir/server.crt et $CertDir/server.key"
} finally {
    Pop-Location
}
