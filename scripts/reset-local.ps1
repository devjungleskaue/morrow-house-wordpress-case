$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$composeProject = 'morrow-house-wordpress-case-local'

function Invoke-MorrowHouseCompose {
  param(
    [Parameter(Mandatory = $true)] [string] $Operation,
    [Parameter(Mandatory = $true)] [string[]] $ComposeArguments
  )

  & docker compose --project-name $composeProject @ComposeArguments
  $exitCode = $LASTEXITCODE
  if ($exitCode -ne 0) {
    throw "Docker Compose failed while $Operation (exit code $exitCode)."
  }
}

if (!(Test-Path -LiteralPath (Join-Path $projectRoot 'blueprint.json')) -or !(Test-Path -LiteralPath (Join-Path $projectRoot 'wp-content\themes\morrow-house\style.css'))) {
  throw 'Refusing reset: Morrow House repository markers were not found.'
}
$originalWordPressPort = [Environment]::GetEnvironmentVariable('WORDPRESS_PORT', 'Process')
Push-Location $projectRoot
try {
  if (!(Test-Path -LiteralPath '.env')) { Copy-Item -LiteralPath '.env.example' -Destination '.env' }
  $wordpressPort = $env:WORDPRESS_PORT
  if ([string]::IsNullOrWhiteSpace($wordpressPort)) {
    $portLine = Get-Content -LiteralPath '.env' | Where-Object { $_ -match '^\s*WORDPRESS_PORT\s*=' } | Select-Object -Last 1
    if ([string]::IsNullOrWhiteSpace($portLine)) { throw 'WORDPRESS_PORT is missing from .env.' }
    $wordpressPort = ($portLine -split '=', 2)[1].Trim()
  }
  if ($wordpressPort -notmatch '^\d{1,5}$' -or [int] $wordpressPort -lt 1 -or [int] $wordpressPort -gt 65535) {
    throw "WORDPRESS_PORT must be an integer from 1 to 65535; received '$wordpressPort'."
  }
  $env:WORDPRESS_PORT = $wordpressPort
  $localUrl = "http://localhost:$wordpressPort"
  Write-Warning "This reset removes only Docker volumes in the '$composeProject' project."
  Invoke-MorrowHouseCompose -Operation 'removing the existing local stack' -ComposeArguments @('down', '--volumes', '--remove-orphans')
  Invoke-MorrowHouseCompose -Operation 'starting WordPress and MariaDB' -ComposeArguments @('up', '-d', 'db', 'wordpress')
  $ready = $false
  foreach ($attempt in 1..60) {
    try { $response = Invoke-WebRequest -Uri "$localUrl/wp-admin/install.php" -UseBasicParsing -TimeoutSec 3; if ($response.StatusCode -lt 500) { $ready = $true; break } } catch {}
    Start-Sleep -Seconds 2
  }
  if (!$ready) { throw 'WordPress did not become ready within 120 seconds.' }
  Invoke-MorrowHouseCompose -Operation 'installing WordPress' -ComposeArguments @('run', '--rm', 'cli', 'wp', 'core', 'install', "--url=$localUrl", '--title=Morrow House', '--admin_user=morrow_admin', '--admin_password=morrow_admin_local', '--admin_email=admin@morrowhouse.example', '--skip-email')
  Invoke-MorrowHouseCompose -Operation 'installing required plugins' -ComposeArguments @('run', '--rm', 'cli', 'wp', 'plugin', 'install', 'https://downloads.wordpress.org/plugin/woocommerce.10.9.4.zip', 'https://downloads.wordpress.org/plugin/elementor.4.2.1.zip', '--activate')
  Invoke-MorrowHouseCompose -Operation 'activating the Morrow House theme' -ComposeArguments @('run', '--rm', 'cli', 'wp', 'theme', 'activate', 'morrow-house')
  Invoke-MorrowHouseCompose -Operation 'activating the Morrow House plugin' -ComposeArguments @('run', '--rm', 'cli', 'wp', 'plugin', 'activate', 'morrow-house-core')
  Invoke-MorrowHouseCompose -Operation 'seeding Morrow House content' -ComposeArguments @('run', '--rm', 'cli', 'wp', 'eval', "require '/project/scripts/seed.php';")
  Write-Host "Morrow House is ready at $localUrl"
} finally {
  [Environment]::SetEnvironmentVariable('WORDPRESS_PORT', $originalWordPressPort, 'Process')
  Pop-Location
}
