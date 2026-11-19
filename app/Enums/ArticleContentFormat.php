<?php

namespace App\Enums;

enum ArticleContentFormat: string
{
    case LegacyHtml = 'legacy_html';
    case BlocksV1 = 'blocks_v1';
}
