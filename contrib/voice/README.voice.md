# Voice support on Linux

Not server related, but useful.

Voice is not supported on Linux, or at least some distributions.

## You can enable it easily with wine.

Wine is a light emulator allowing to run some Windows applications on Linux or, like in this case, use Windows specific libraries.

1. If not yet present, install wine package and run `winecfg`. Default settings should be fine.
2. In viewer Debug Settings (Ctrl + Alt + Shift + S), look for "wine". Set`FSLinuxEnableWin64VoiceProxy`, to true.
3. Logout and come back.

That's it.

## Without Wine

Several alternatives (including this one) are described on FireStorm Wiki: <https://wiki.firestormviewer.org/fs_linuxvoice>

There is also a modified library `libidn.so.11` provided here. It could work, but... BUT....

- I can't remember where you need to put it.
- It's a modified library. I have no idea of the author, nor their qualifications to build a library.
- use at your own risk.

## **tl;tr**

Wine solution is cool, it's fast, it's light, it's flawless.

In any case, you might have issues with jackd, maybe that's too much for the viewer to handle.
