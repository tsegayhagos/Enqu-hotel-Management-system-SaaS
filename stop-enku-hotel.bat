@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "Stop-Process -Name php, mysqld -Force -ErrorAction SilentlyContinue; Write-Host 'Enku Hotel servers stopped successfully.' -ForegroundColor Green; Start-Sleep -Seconds 2"
