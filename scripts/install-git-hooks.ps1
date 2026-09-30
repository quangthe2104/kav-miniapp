$root = Split-Path -Parent $PSScriptRoot
$src = Join-Path $root '.githooks\commit-msg'
$dst = Join-Path $root '.git\hooks\commit-msg'

Copy-Item -Force $src $dst
Write-Host "Installed commit-msg hook -> $dst"
