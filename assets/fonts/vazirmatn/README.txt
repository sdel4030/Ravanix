Vazirmatn font — file location folder

This folder is intended for the Vazirmatn font files, but due to no internet
access in the environment where this plugin was built, the actual font files
have not been placed here. The plugin's CSS (in assets/css/ravanix-admin.css,
assets/css/ravanix-frontend.css, and the print/PDF section) is already wired
up to use these files — you just need to place the font files themselves,
with the exact names below, in this same folder so the whole plugin
automatically uses Vazirmatn everywhere.

Required file names (woff2 format, for different weights):
  - Vazirmatn-Regular.woff2      (weight 400)
  - Vazirmatn-Medium.woff2       (weight 500)
  - Vazirmatn-SemiBold.woff2     (weight 600)
  - Vazirmatn-Bold.woff2         (weight 700)

Official, free source (SIL Open Font License):
https://github.com/rastikerdar/vazirmatn

Download the woff2 files for the Regular, Medium, SemiBold, and Bold weights
from the GitHub repository above (or from Google Fonts:
https://fonts.google.com/specimen/Vazirmatn), and copy them into this same
folder (assets/fonts/vazirmatn/) using exactly these names. If the names of
the downloaded files differ, simply rename them, or update the names in the
CSS files mentioned above (look for the @font-face sections).

If these files are not present, the browser automatically falls back to
alternate fonts (Tahoma, IRANSans, or the system default font); that is, a
missing file does not break the plugin — Persian text just keeps looking the
way it did before.
