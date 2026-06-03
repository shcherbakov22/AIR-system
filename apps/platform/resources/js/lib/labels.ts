export const labelStudentStatus = (status: string): string => {
    switch (status) {
        case 'active':
            return 'active';
        case 'paused':
            return 'paused';
        case 'unfinished':
            return 'unfinished';
        default:
            return status;
    }
};

export const labelTaskSessionSourceType = (sourceType: string): string => {
    switch (sourceType) {
        case 'schedule':
            return 'schedule';
        case 'assignment':
            return 'assignment';
        case 'ad_hoc':
            return 'own timer';
        default:
            return sourceType;
    }
};

export const labelViolationResolutionAction = (action: string): string => {
    switch (action) {
        case 'resolved':
            return 'resolved';
        case 'waived':
            return 'waived';
        case 'false_positive':
            return 'false positive';
        default:
            return action;
    }
};

export const labelPenaltyTransactionType = (type: string): string => {
    switch (type) {
        case 'manual_charge':
            return 'manual charge';
        case 'manual_credit':
            return 'manual credit';
        case 'violation_charge':
            return 'violation charge';
        default:
            return type;
    }
};

export const labelImportRunStatus = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'pending';
        case 'running':
            return 'running';
        case 'completed':
            return 'completed';
        case 'failed':
            return 'failed';
        default:
            return status;
    }
};

export const labelImportIssueSeverity = (severity: string): string => {
    switch (severity) {
        case 'low':
            return 'low';
        case 'medium':
            return 'medium';
        case 'high':
            return 'high';
        case 'critical':
            return 'critical';
        default:
            return severity;
    }
};
