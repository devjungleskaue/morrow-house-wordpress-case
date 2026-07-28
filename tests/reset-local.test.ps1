$ErrorActionPreference = 'Stop'

function Assert-True {
  param(
    [Parameter(Mandatory = $true)] [bool] $Condition,
    [Parameter(Mandatory = $true)] [string] $Message
  )

  if (!$Condition) { throw "Reset test failed: $Message" }
}

$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$resetScript = Join-Path $repositoryRoot 'scripts\reset-local.ps1'
$envPath = Join-Path $repositoryRoot '.env'
$temporaryRoot = Join-Path ([IO.Path]::GetTempPath()) ("morrow-house-reset-test-{0}" -f [guid]::NewGuid().ToString('N'))
$dockerLog = Join-Path $temporaryRoot 'docker.log'
$fakeDocker = Join-Path $temporaryRoot 'docker.cmd'
$originalPath = $env:PATH
$originalPort = [Environment]::GetEnvironmentVariable('WORDPRESS_PORT', 'Process')
$originalDockerExit = [Environment]::GetEnvironmentVariable('FAKE_DOCKER_EXIT', 'Process')
$originalDockerLog = [Environment]::GetEnvironmentVariable('FAKE_DOCKER_LOG', 'Process')
$startingLocation = (Get-Location).Path
$createdEnv = $false

function Invoke-WebRequest {
  param(
    [string] $Uri,
    [switch] $UseBasicParsing,
    [int] $TimeoutSec
  )

  return [pscustomobject] @{ StatusCode = 200 }
}

function Invoke-ResetProbe {
  param([Parameter(Mandatory = $true)] [int] $DockerExitCode)

  Clear-Content -LiteralPath $dockerLog
  $env:FAKE_DOCKER_EXIT = [string] $DockerExitCode
  $env:WORDPRESS_PORT = '43123'
  $output = New-Object 'System.Collections.Generic.List[string]'
  $caught = $null

  try {
    & $resetScript *>&1 | ForEach-Object { [void] $output.Add($_.ToString()) }
  } catch {
    $caught = $_
  }

  return [pscustomobject] @{
    Error = $caught
    Output = $output.ToArray()
    Commands = @(Get-Content -LiteralPath $dockerLog)
    Location = (Get-Location).Path
    Port = [Environment]::GetEnvironmentVariable('WORDPRESS_PORT', 'Process')
  }
}

try {
  [void] (New-Item -ItemType Directory -Path $temporaryRoot)
  [void] (New-Item -ItemType File -Path $dockerLog)
  Set-Content -LiteralPath $fakeDocker -Encoding ASCII -Value @(
    '@echo off',
    '>>"%FAKE_DOCKER_LOG%" echo %*',
    'exit /b %FAKE_DOCKER_EXIT%'
  )
  if (!(Test-Path -LiteralPath $envPath)) {
    Set-Content -LiteralPath $envPath -Encoding ASCII -Value 'WORDPRESS_PORT=8080'
    $createdEnv = $true
  }
  $env:FAKE_DOCKER_LOG = $dockerLog
  $env:PATH = "$temporaryRoot$([IO.Path]::PathSeparator)$originalPath"

  $failed = Invoke-ResetProbe -DockerExitCode 7
  Assert-True ($null -ne $failed.Error) 'a non-zero native Docker exit must throw.'
  Assert-True ($failed.Error.Exception.Message -match 'exit code 7') 'the thrown error must retain the native exit code.'
  Assert-True ($failed.Error.Exception.Message -match 'removing the existing local stack') 'the thrown error must identify the failed operation.'
  Assert-True (-not (($failed.Output -join "`n") -match 'Morrow House is ready')) 'failure must not print the ready message.'
  Assert-True ($failed.Commands.Count -eq 1) 'failure must stop before any later Docker command.'
  Assert-True ($failed.Commands[0] -match '^compose --project-name morrow-house-wordpress-case-local down ') 'the failing operation must be the first scoped Compose command.'
  Assert-True ($failed.Location -eq $startingLocation) 'failure must restore the caller location.'
  Assert-True ($failed.Port -eq '43123') 'failure must restore the caller WORDPRESS_PORT.'

  $succeeded = Invoke-ResetProbe -DockerExitCode 0
  Assert-True ($null -eq $succeeded.Error) 'zero native exits must complete without throwing.'
  Assert-True (($succeeded.Output -join "`n") -match 'Morrow House is ready at http://localhost:43123') 'success must print the derived ready URL.'
  Assert-True ($succeeded.Commands.Count -eq 7) 'success must execute exactly seven scoped Compose commands.'
  Assert-True ($succeeded.Commands[3] -match 'elementor\.4\.2\.1\.zip') 'plugin installation must pin Elementor 4.2.1.'
  Assert-True ($succeeded.Location -eq $startingLocation) 'success must restore the caller location.'
  Assert-True ($succeeded.Port -eq '43123') 'success must restore the caller WORDPRESS_PORT.'

  Write-Host 'Reset local native command tests passed.'
} finally {
  $env:PATH = $originalPath
  [Environment]::SetEnvironmentVariable('WORDPRESS_PORT', $originalPort, 'Process')
  [Environment]::SetEnvironmentVariable('FAKE_DOCKER_EXIT', $originalDockerExit, 'Process')
  [Environment]::SetEnvironmentVariable('FAKE_DOCKER_LOG', $originalDockerLog, 'Process')
  Set-Location -LiteralPath $startingLocation
  if ($createdEnv) { Remove-Item -LiteralPath $envPath -Force }
  if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
}
