Get-ChildItem *.php | ForEach-Object {
    (Get-Content $_.FullName) -replace 'ERN_NO', 'PRN_NO' | Set-Content $_.FullName
}
