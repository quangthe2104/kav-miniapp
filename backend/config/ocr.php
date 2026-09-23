<?php

return [
    /*
    | Relative crop boxes (percent of image width/height) for Đồng ý / Không đồng ý.
    | Prefer per-Form ocr_layout_json calibration; these are global fallbacks.
    | Tuned for KAV consent template (checkboxes ~ left column, mid-lower page).
    */
    'paper_checkbox' => [
        'agree' => [
            'x' => 10.5,
            'y' => 58.5,
            'w' => 5.0,
            'h' => 3.5,
        ],
        'disagree' => [
            'x' => 10.5,
            'y' => 63.0,
            'w' => 5.0,
            'h' => 3.5,
        ],
        // Minimum fill ratio difference to pick a side; else unknown
        'min_delta' => 0.025,
        // Absolute darkness threshold (0–1) — too light → unknown
        'min_ink' => 0.035,
    ],
];
