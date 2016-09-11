@echo off
:top
H:\microsyf\wamp\bin\php\php5.5.11\php -c H:/gnioty_e/pn/php55.ini -f mapdlcron2.php
timeout /T 5 /NOBREAK 
rem goto top