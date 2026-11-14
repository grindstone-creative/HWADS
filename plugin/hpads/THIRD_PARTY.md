# Third-party software

`assets/vendor/qrcodegen.js` contains Project Nayuki's QR Code Generator, compiled from TypeScript and wrapped to expose only `globalThis.HPADSQRCode`. The full upstream MIT copyright/license notice is preserved in that file.

- Author: Project Nayuki
- Project: https://www.nayuki.io/page/qr-code-generator-library
- Source: https://github.com/nayuki/QR-Code-generator/blob/master/typescript-javascript/qrcodegen.ts
- Retrieved source blob: `c4191ac0b5f8a24d87fd9340ceaa2d3b07d0231a`
- License: MIT
- Runtime dependencies: none. No CDN, QR web service or external request is used by the plugin.

The source/testing archive also contains the upstream Python reference (`python/qrcodegen.py`, blob `0eb24eaee4c4a164ec4c332ec87428630883c317`) for cross-language test vectors. It is not a runtime requirement and is not included in the install ZIP.

Playlist card interaction and styling are adapted from the user-supplied Slidestone 0.1.11 source by Grindstone Creative. Slidestone itself is not bundled or required.
