@echo off
echo Building distribution to SVN trunk...

:: Set your SVN trunk path here
set SVN_TRUNK=C:\Users\jessa\Documents\WordPressDevelopment\plugins\MailChimpTagsForWooCommerce\svn\trunk

:: Clear trunk contents (but not the folder itself)
if exist "%SVN_TRUNK%" (
    del /Q "%SVN_TRUNK%\*.*" 2>nul
    for /D %%p in ("%SVN_TRUNK%\*") do rmdir /S /Q "%%p"
)

:: Copy plugin files
copy class-mctwc-mailchimp-tags-integration.php "%SVN_TRUNK%\"
copy mctwc-tags-for-mailchimp.php "%SVN_TRUNK%\"
copy readme.txt "%SVN_TRUNK%\"
copy uninstall.php "%SVN_TRUNK%\"
copy composer.json "%SVN_TRUNK%\"
copy composer.lock "%SVN_TRUNK%\"
if exist composer.lock (
    echo composer.lock found
) else (
    echo composer.lock NOT found in %CD%
)
xcopy js "%SVN_TRUNK%\js\" /E /I

:: Create temp folder for composer install
if exist "..\purchase-tagger-temp" rmdir /S /Q "..\purchase-tagger-temp"
mkdir "..\purchase-tagger-temp"
copy composer.json ..\purchase-tagger-temp\
copy composer.lock ..\purchase-tagger-temp\
cd ..\purchase-tagger-temp
call composer install --no-dev

:: Copy vendor to trunk
xcopy vendor "%SVN_TRUNK%\vendor\" /E /I

:: Clean up unnecessary vendor files
rmdir /S /Q "%SVN_TRUNK%\vendor\drewm\mailchimp-api\scripts"

:: Clean up temp folder
cd ..
rmdir /S /Q purchase-tagger-temp

echo.
echo Build complete! Files are in SVN trunk.
echo Next steps:
echo   1. Create tag folder (e.g., tags\1.1.0)
echo   2. Copy trunk contents to the tag folder
echo   3. SVN Commit
pause