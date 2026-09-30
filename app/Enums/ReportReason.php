<?php

namespace App\Enums;

enum ReportReason: string
{
    case Harassment = 'harassment';
    case Spam = 'spam';
    case Fraud = 'fraud';
    case InappropriateContent = 'inappropriate_content';
    case Other = 'other';
}
