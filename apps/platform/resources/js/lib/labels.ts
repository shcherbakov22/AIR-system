export const labelStudentStatus = (status: string): string => {
    switch (status) {
        case 'active':
            return 'активен';
        case 'paused':
            return 'пауза';
        default:
            return status;
    }
};

export const labelTaskSessionSourceType = (sourceType: string): string => {
    switch (sourceType) {
        case 'schedule':
            return 'расписание';
        case 'assignment':
            return 'назначение';
        case 'ad_hoc':
            return 'свой таймер';
        default:
            return sourceType;
    }
};

export const labelViolationResolutionAction = (action: string): string => {
    switch (action) {
        case 'resolved':
            return 'решено';
        case 'waived':
            return 'отменено';
        default:
            return action;
    }
};

export const labelPenaltyTransactionType = (type: string): string => {
    switch (type) {
        case 'manual_charge':
            return 'ручное начисление';
        case 'manual_credit':
            return 'ручное списание';
        case 'violation_charge':
            return 'начисление за нарушение';
        default:
            return type;
    }
};

export const labelImportRunStatus = (status: string): string => {
    switch (status) {
        case 'pending':
            return 'ожидает';
        case 'running':
            return 'выполняется';
        case 'completed':
            return 'завершён';
        case 'failed':
            return 'ошибка';
        default:
            return status;
    }
};

export const labelImportIssueSeverity = (severity: string): string => {
    switch (severity) {
        case 'low':
            return 'низкая';
        case 'medium':
            return 'средняя';
        case 'high':
            return 'высокая';
        case 'critical':
            return 'критическая';
        default:
            return severity;
    }
};
