@echo off
echo Building distribution folder...

:: Remove old dist folder if it exists
if exist "..\purchase-tagger-dist" rmdir /S /Q "..\purchase-tagger-dist"

:: Create fresh dist folder
mkdir "..\purchase-tagger-dist"

:: Copy plugin files
copy class-mctwc-mailchimp-tags-integration.php ..\purchase-tagger-dist\
copy mctwc-tags-for-mailchimp.php ..\purchase-tagger-dist\
copy readme.txt ..\purchase-tagger-dist\
copy uninstall.php ..\purchase-tagger-dist\
copy composer.json ..\purchase-tagger-dist\
xcopy js ..\purchase-tagger-dist\js\ /E /I

:: Install production dependencies
cd ..\purchase-tagger-dist
call composer install --no-dev

:: Clean up
del composer.json composer.lock
rmdir /S /Q vendor\drewm\mailchimp-api\scripts

echo.
echo Build complete! Zip the purchase-tagger-dist folder for upload.
pause