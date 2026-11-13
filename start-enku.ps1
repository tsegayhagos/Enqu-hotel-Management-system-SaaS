# Enku Hotel Management SaaS Launcher
$Host.UI.RawUI.WindowTitle = "Enku Hotel Management System SaaS"
try { [Console]::OutputEncoding = [System.Text.UTF8Encoding]::new() } catch {}

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "       Enku Hotel Management System SaaS Launcher       " -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

$projectDir = "C:\laragon\www\Enqu-hotel-Management-system-SaaS"
$phpDir = "C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64"
$mysqlDir = "C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin"
$env:Path = "$phpDir;$mysqlDir;$env:Path"
Set-Location $projectDir

# 1. Check MySQL
Write-Host "[1/3] Checking MySQL database..." -ForegroundColor Yellow
$mysqlPort = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if (-not $mysqlPort) {
    Write-Host "      Starting MySQL server..." -ForegroundColor Green
    Start-Process -FilePath "$mysqlDir\mysqld.exe" -ArgumentList '--defaults-file="C:\laragon\bin\mysql\mysql-8.4.3-winx64\my.ini"', '--console' -WindowStyle Hidden
    Start-Sleep -Seconds 4
} else {
    Write-Host "      MySQL is already running." -ForegroundColor Green
}

# 2. Check Laravel Web Server
Write-Host "[2/3] Checking Laravel web server..." -ForegroundColor Yellow
$servePort = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if (-not $servePort) {
    Write-Host "      Starting Laravel server on http://127.0.0.1:8000..." -ForegroundColor Green
    Start-Process -FilePath "$phpDir\php.exe" -ArgumentList "artisan", "serve", "--host=127.0.0.1", "--port=8000" -WorkingDirectory $projectDir -WindowStyle Hidden
    Start-Sleep -Seconds 3
} else {
    Write-Host "      Laravel server is already running." -ForegroundColor Green
}

# 3. Open Browser
Write-Host "[3/3] Opening browser to http://127.0.0.1:8000/login..." -ForegroundColor Yellow
Start-Process "http://127.0.0.1:8000/login"

Write-Host ""
Write-Host "========================================================" -ForegroundColor Green
Write-Host "   [✓] System is Running! Browser opened.               " -ForegroundColor Green
Write-Host "   URL: http://127.0.0.1:8000/login                     " -ForegroundColor Green
Write-Host "========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "   --- Login Accounts (Password for all: password) ---" -ForegroundColor White
Write-Host "   - Hotel Manager : manager@enku.test" -ForegroundColor White
Write-Host "   - Platform Admin: admin@enku.test" -ForegroundColor White
Write-Host "   - Front Desk    : frontdesk@enku.test" -ForegroundColor White
Write-Host "   - Laundry       : laundry@enku.test" -ForegroundColor White
Write-Host "   - Housekeeping  : housekeeping@enku.test" -ForegroundColor White
Write-Host "   - POS Cashier   : cashier@enku.test" -ForegroundColor White
Write-Host "   - Night Auditor : accountant@enku.test" -ForegroundColor White
Write-Host ""
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   ማስታወሻ፡ ሲስተሙን እየተጠቀምክ ይህን መስኮት ክፍት አድርገው።" -ForegroundColor Yellow
Write-Host "   ሲስተሙን ለማቆም ይህን መስኮት ዝጋው።" -ForegroundColor Yellow
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""

while ($true) {
    Start-Sleep -Seconds 60
}
