# e-Clinic server: one-shot local setup after pulling new modules.
# Run from the server folder:  powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
# Add -Client to also build the Angular client next door, -SkipTests to skip tests/analysis.
param([switch]$Client, [switch]$SkipTests)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host '== 1. Removing obsolete Laravel defaults (replaced by the Identity module)' -ForegroundColor Cyan
$stale = @(
  'database\migrations\0001_01_01_000000_create_users_table.php',
  'database\migrations\0001_01_01_000003_create_personal_access_tokens_table.php',
  'database\migrations\0001_01_01_000004_create_permission_tables.php',
  'app\Models\User.php',
  'database\factories\UserFactory.php',
  'app\Shared\Bulk\RunBulkActionJob.php'
)
foreach ($f in $stale) {
  if (Test-Path $f) { Remove-Item $f; Write-Host "  removed $f" } else { Write-Host "  (already gone) $f" }
}
if ((Test-Path 'app\Models') -and -not (Get-ChildItem 'app\Models')) { Remove-Item 'app\Models' }

Write-Host '== 2. Checking .env' -ForegroundColor Cyan
if (-not (Test-Path '.env')) { Copy-Item '.env.example' '.env'; php artisan key:generate }
$envText = Get-Content '.env' -Raw
$needed = [ordered]@{
  'CLIENT_URL'           = 'http://localhost:4200'
  'BLIND_INDEX_KEY'      = [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
  'SUPER_ADMIN_EMAIL'    = 'admin@eclinic.local'
  'SUPER_ADMIN_PASSWORD' = 'Admin-Pass-2026!'
}
foreach ($k in $needed.Keys) {
  if ($envText -notmatch "(?m)^$k=.+") {
    $envText = ($envText -replace "(?m)^$k=.*\r?\n?", '') + "`n$k=$($needed[$k])"
    Write-Host "  added $k"
  }
}
Set-Content '.env' $envText.TrimEnd() -NoNewline

Write-Host '== 3. Composer packages' -ForegroundColor Cyan
composer update tecnickcom/tc-lib-barcode pragmarx/google2fa bacon/bacon-qr-code propaganistas/laravel-phone --with-all-dependencies
composer dump-autoload

Write-Host '== 4. Fresh database + seed' -ForegroundColor Cyan
php artisan optimize:clear
php artisan migrate:fresh --seed --force

if (-not $SkipTests) {
  Write-Host '== 5. Tests and static analysis' -ForegroundColor Cyan
  php artisan test --compact
  composer analyse
}

if ($Client) {
  Write-Host '== 6. Angular client' -ForegroundColor Cyan
  Push-Location '..\e_clinic_client'
  npm install
  npm run build
  Pop-Location
}

Write-Host 'Done. Start: php artisan serve | php artisan queue:work | php artisan schedule:work' -ForegroundColor Green
