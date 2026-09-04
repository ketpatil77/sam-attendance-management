@echo off
setlocal

set "APP_ROOT=%~dp0.."
set "APACHE_HOME=%APP_ROOT%\portable\Apache24"
set "HTTPD=%APACHE_HOME%\bin\httpd.exe"
set "CONF=%APACHE_HOME%\conf\httpd.conf"

if not exist "%HTTPD%" (
  echo Apache not found at: "%HTTPD%"
  echo Put portable Apache at: "%APACHE_HOME%"
  exit /b 1
)

if not exist "%CONF%" (
  echo Apache config not found at: "%CONF%"
  exit /b 1
)

"%HTTPD%" -k start -f "%CONF%"
if errorlevel 1 (
  echo Failed to start Apache.
  exit /b 1
)

echo Apache started. Open: http://127.0.0.1:8088/
endlocal
