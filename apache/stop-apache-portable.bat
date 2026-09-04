@echo off
setlocal

set "APP_ROOT=%~dp0.."
set "APACHE_HOME=%APP_ROOT%\portable\Apache24"
set "HTTPD=%APACHE_HOME%\bin\httpd.exe"
set "CONF=%APACHE_HOME%\conf\httpd.conf"

if not exist "%HTTPD%" (
  echo Apache not found at: "%HTTPD%"
  exit /b 1
)

"%HTTPD%" -k stop -f "%CONF%"
if errorlevel 1 (
  echo Failed to stop Apache.
  exit /b 1
)

echo Apache stopped.
endlocal
