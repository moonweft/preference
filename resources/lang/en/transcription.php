<?php

return [
    'engine' => 'Speech recognition engine',
    'engine_help' => 'Default: 16k_zh (Mandarin). Use 16k_en for English or 16k_yue for Cantonese. Enter a supported Tencent ASR engine ID.',
    'description' => 'Tencent Cloud speech-to-text for audio up to 60 seconds, with a 3 MiB limit after Base64 encoding. Enable the speech recognition service in Tencent Cloud. Credentials are encrypted; leaving them blank retains the saved values. Removing credentials clears both SecretId and SecretKey. This does not configure speech synthesis or image recognition.',
];
