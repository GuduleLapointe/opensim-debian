
if exist ..\..\Aurora\nul goto AURORA 


:OPENSIM
..\..\bin\Prebuild.exe /target vs2008
echo C:\WINDOWS\Microsoft.NET\Framework\v3.5\msbuild opensim.sln > compile.bat
goto END


:AURORA
..\..\bin\Prebuild.exe /target vs2008 /targetframework v3_5 
echo C:\WINDOWS\Microsoft.NET\Framework\v3.5\msbuild Aurora.sln > compile.bat


:END
