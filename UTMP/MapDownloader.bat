@echo off
:top
php  -f mapdlcron2.php
timeout /T 5 /NOBREAK 
rem goto top