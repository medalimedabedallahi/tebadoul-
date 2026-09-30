<?php

namespace App\Enums;

/**
 * Sensitive operations retained in the audit trail.
 */
enum AuditAction: string
{
    case UserSuspended = 'user_suspended';
    case UserReinstated = 'user_reinstated';
    case ReportResolved = 'report_resolved';
    case ReportDismissed = 'report_dismissed';
    case AccountDeleted = 'account_deleted';
}
