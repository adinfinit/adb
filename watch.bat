set PHP=..\..\php-7.1.3
set TEST=%CD%\adb.run_tests.php

cd %PHP%
watchrun php.exe %TEST%c