param(
    [Parameter(Mandatory=$true)][string]$Php,
    [Parameter(Mandatory=$true)][string]$WpCli
)
$ErrorActionPreference = 'Stop'
$jwProjectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$jwManifest = Get-Content -LiteralPath (Join-Path $jwProjectRoot 'config/dependencies.json') -Raw | ConvertFrom-Json
function Invoke-JwWp([string[]]$Arguments) {
    & $Php $WpCli @Arguments "--path=$jwProjectRoot"
    if ($LASTEXITCODE -ne 0) { throw 'WP-CLI failed. No automatic rollback or destructive cleanup was performed.' }
}
if (-not (Test-Path -LiteralPath (Join-Path $jwProjectRoot 'wp-includes/version.php'))) {
    Invoke-JwWp @('core','download',"--version=$($jwManifest.wordpress)",'--locale=pt_BR','--skip-content')
}
if (-not (Test-Path -LiteralPath (Join-Path $jwProjectRoot 'wp-config.php'))) {
    Write-Output 'Core downloaded. Configure the private wp-config.php and install the empty database with WP-CLI, then run this script again.'
    exit 0
}
Invoke-JwWp @('core','is-installed')
foreach ($jwPlugin in $jwManifest.plugins.PSObject.Properties) {
    Invoke-JwWp @('plugin','install',$jwPlugin.Name,"--version=$($jwPlugin.Value)",'--activate')
}
Invoke-JwWp @('plugin','activate','jw-core')
Invoke-JwWp @('theme','activate','jw-headless')
Write-Output 'Dependencies installed. This script does not create or overwrite editorial content or configure deployment.'
