<?php

namespace App\Services;

use App\Models\BrowserAccessRequest;
use App\Models\BrowserPolicyRule;
use App\Models\BrowserVisitLog;
use App\Models\Student;
use App\Models\StudentDevice;
use App\Models\TaskTemplate;
use App\Models\TaskSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BrowserAccountabilityPolicyService
{
    /**
     * Common multi-label public suffixes. This keeps domain-tree matching sane
     * without adding a runtime dependency for the browser accountability MVP.
     */
    private const MULTI_LABEL_PUBLIC_SUFFIXES = [
        'ac.uk',
        'co.jp',
        'co.uk',
        'com.au',
        'com.br',
        'com.mx',
        'com.tr',
        'com.tw',
        'com.ua',
        'edu.au',
        'gov.uk',
        'net.au',
        'org.au',
        'org.uk',
    ];

    public function __construct(
        private readonly BlockedResourceViolationService $blockedResourceViolationService,
    ) {}

    public function policyForDevice(StudentDevice $device): array
    {
        $student = $device->student()->firstOrFail();
        $mode = $this->modeForDevice($device);
        $activeTaskSession = $this->activeTaskSession($student);
        $rules = $this->activeRules($student, $activeTaskSession?->task_template_id);
        $updatedAt = $rules
            ->pluck('updated_at')
            ->filter()
            ->sort()
            ->last();

        return [
            'mode' => $mode,
            'default_unblock_scope' => 'domain_tree',
            'log_full_url' => true,
            'active_task' => $activeTaskSession ? [
                'task_session_id' => $activeTaskSession->id,
                'task_template_id' => $activeTaskSession->task_template_id,
                'title' => $activeTaskSession->task_title_snapshot,
            ] : null,
            'rules' => $rules
                ->map(fn (BrowserPolicyRule $rule) => $this->rulePayload($rule))
                ->values()
                ->all(),
            'updated_at' => $updatedAt instanceof Carbon ? $updatedAt->toAtomString() : now()->toAtomString(),
        ];
    }

    public function evaluate(Student $student, string $url, ?StudentDevice $device = null): array
    {
        $host = $this->hostFromUrl($url);
        $domain = $this->registrableDomain($host);
        $mode = $device ? $this->modeForDevice($device) : $this->modeForStudent($student);
        $activeTaskSession = $this->activeTaskSession($student);
        $rules = $this->activeRules($student, $activeTaskSession?->task_template_id);
        if ($this->usesExactUrlRule($url)) {
            $effect = $mode === 'whitelist' ? 'allow' : 'block';
            $matchedRule = $this->matchingRule($rules, $url, $host, $domain, $effect, 'exact_url');
            $allowed = match ($mode) {
                'whitelist' => $matchedRule !== null,
                'blacklist' => $matchedRule === null,
                default => true,
            };
        } else {
            $matchedRule = $this->matchingRule($rules, $url, $host, $domain, $mode === 'whitelist' ? 'allow' : 'block');

            $allowed = match ($mode) {
                'whitelist' => $matchedRule !== null,
                'blacklist' => $matchedRule === null,
                default => true,
            };
        }

        return [
            'mode' => $mode,
            'decision' => $allowed ? 'allowed' : 'blocked',
            'allowed' => $allowed,
            'host' => $host,
            'registrable_domain' => $domain,
            'matched_rule' => $matchedRule,
        ];
    }

    public function logVisit(StudentDevice $device, string $url, ?string $pageTitle = null, array $meta = []): BrowserVisitLog
    {
        $student = $device->student()->firstOrFail();
        $evaluation = $this->evaluate($student, $url, $device);

        $visit = BrowserVisitLog::create([
            'student_id' => $student->id,
            'student_device_id' => $device->id,
            'matched_rule_id' => $evaluation['matched_rule']?->id,
            'mode' => $evaluation['mode'],
            'decision' => $evaluation['decision'],
            'url' => Str::limit($url, 2048, ''),
            'host' => $evaluation['host'],
            'registrable_domain' => $evaluation['registrable_domain'],
            'page_title' => $pageTitle !== null ? Str::limit($pageTitle, 255, '') : null,
            'meta' => $meta,
            'visited_at' => now(),
        ]);

        if ($visit->decision === 'blocked') {
            $this->blockedResourceViolationService->recordBlockedWebsiteVisit($visit);
        }

        return $visit;
    }

    public function createAccessRequest(StudentDevice $device, string $url, ?string $reason = null): BrowserAccessRequest
    {
        $student = $device->student()->firstOrFail();
        $host = $this->hostFromUrl($url);
        $activeTaskSession = $this->activeTaskSession($student);
        $evaluation = $this->evaluate($student, $url, $device);
        $matchedAllowRule = $evaluation['matched_rule'] instanceof BrowserPolicyRule
            && $evaluation['matched_rule']->effect === 'allow'
            ? $evaluation['matched_rule']
            : null;

        return BrowserAccessRequest::create([
            'student_id' => $device->student_id,
            'student_device_id' => $device->id,
            'task_template_id' => $activeTaskSession?->task_template_id,
            'requested_url' => Str::limit($url, 2048, ''),
            'host' => $host,
            'registrable_domain' => $this->registrableDomain($host),
            'reason' => $reason !== null ? trim($reason) : null,
            'status' => $matchedAllowRule ? 'approved' : ($activeTaskSession?->task_template_id ? 'pending' : 'denied'),
            'approved_rule_id' => $matchedAllowRule?->id,
            'mentor_note' => $matchedAllowRule
                ? 'Automatically approved because this URL is already allowed for the current task.'
                : ($activeTaskSession?->task_template_id ? null : 'Automatically denied because no task was active when the request was sent.'),
            'decided_at' => ($matchedAllowRule || ! $activeTaskSession?->task_template_id) ? now() : null,
        ]);
    }

    public function approveRequest(
        BrowserAccessRequest $accessRequest,
        User $mentor,
        ?Carbon $expiresAt = null,
        ?string $mentorNote = null,
        bool $global = false,
    ): BrowserPolicyRule {
        if (! $global && ! $accessRequest->task_template_id) {
            $this->denyRequest(
                $accessRequest,
                $mentor,
                $mentorNote ?: 'Denied because no task was active when the request was sent.',
            );

            throw new \RuntimeException('Cannot approve a task-scoped browser request without a captured task.');
        }

        $ruleMatch = $this->approvalRuleMatch($accessRequest);

        $rule = BrowserPolicyRule::updateOrCreate(
            [
                'student_id' => $global ? $accessRequest->student_id : null,
                'task_template_id' => $global ? null : $accessRequest->task_template_id,
                'effect' => 'allow',
                'match_type' => $ruleMatch['match_type'],
                'value' => $ruleMatch['value'],
            ],
            [
                'created_by_user_id' => $mentor->id,
                'expires_at' => $expiresAt,
            ],
        );

        $accessRequest->forceFill([
            'status' => 'approved',
            'decided_by_user_id' => $mentor->id,
            'approved_rule_id' => $rule->id,
            'mentor_note' => $mentorNote,
            'expires_at' => $expiresAt,
            'decided_at' => now(),
        ])->save();

        return $rule;
    }

    public function denyRequest(BrowserAccessRequest $accessRequest, User $mentor, ?string $mentorNote = null): void
    {
        $accessRequest->forceFill([
            'status' => 'denied',
            'decided_by_user_id' => $mentor->id,
            'mentor_note' => $mentorNote,
            'decided_at' => now(),
        ])->save();
    }

    public function upsertRule(
        Student $student,
        string $effect,
        string $value,
        ?User $mentor = null,
        ?Carbon $expiresAt = null,
    ): BrowserPolicyRule {
        $host = $this->hostFromUrl($value);
        $domain = $this->registrableDomain($host);
        $activeTaskSession = $effect === 'allow' && $this->modeForStudent($student) === 'whitelist'
            ? $this->activeTaskSession($student)
            : null;

        if ($activeTaskSession?->taskTemplate) {
            return $this->upsertTaskRule(
                $activeTaskSession->taskTemplate,
                $effect,
                $domain,
                $mentor,
                $expiresAt,
            );
        }

        return BrowserPolicyRule::updateOrCreate(
            [
                'student_id' => $student->id,
                'task_template_id' => null,
                'effect' => $effect,
                'match_type' => 'domain_tree',
                'value' => $domain,
            ],
            [
                'created_by_user_id' => $mentor?->id,
                'expires_at' => $expiresAt,
            ],
        );
    }

    public function upsertTaskRule(
        TaskTemplate $taskTemplate,
        string $effect,
        string $value,
        ?User $mentor = null,
        ?Carbon $expiresAt = null,
    ): BrowserPolicyRule {
        $host = $this->hostFromUrl($value);
        $domain = $this->registrableDomain($host);

        return BrowserPolicyRule::updateOrCreate(
            [
                'student_id' => null,
                'task_template_id' => $taskTemplate->id,
                'effect' => $effect,
                'match_type' => 'domain_tree',
                'value' => $domain,
            ],
            [
                'created_by_user_id' => $mentor?->id,
                'expires_at' => $expiresAt,
            ],
        );
    }

    public function syncTaskAllowDomains(TaskTemplate $taskTemplate, array $domains, ?User $mentor = null): void
    {
        $normalizedDomains = collect($domains)
            ->map(fn ($domain) => $this->registrableDomain($this->hostFromUrl((string) $domain)))
            ->filter()
            ->unique()
            ->values();

        $taskTemplate->browserPolicyRules()
            ->whereNull('student_id')
            ->where('effect', 'allow')
            ->where('match_type', 'domain_tree')
            ->whereNotIn('value', $normalizedDomains->all())
            ->delete();

        $normalizedDomains->each(fn (string $domain) => $this->upsertTaskRule(
            $taskTemplate,
            'allow',
            $domain,
            $mentor,
        ));
    }

    public function modeForStudent(Student $student): string
    {
        $mode = (string) $student->devices()
            ->whereIn('internet_access_mode', ['whitelist', 'blacklist'])
            ->value('internet_access_mode');

        return in_array($mode, ['whitelist', 'blacklist'], true) ? $mode : 'blacklist';
    }

    public function modeForDevice(StudentDevice $device): string
    {
        return in_array($device->internet_access_mode, ['whitelist', 'blacklist'], true)
            ? $device->internet_access_mode
            : 'blacklist';
    }

    public function hostFromUrl(string $url): string
    {
        $candidate = trim(Str::lower($url));

        if ($candidate === '') {
            return '';
        }

        if (! str_contains($candidate, '://')) {
            $candidate = 'https://'.$candidate;
        }

        $host = (string) parse_url($candidate, PHP_URL_HOST);

        return trim($host, " \t\n\r\0\x0B.");
    }

    public function registrableDomain(string $host): string
    {
        $host = trim(Str::lower($host), '.');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $labels = array_values(array_filter(explode('.', $host), fn ($label) => $label !== ''));

        if (count($labels) <= 2) {
            return implode('.', $labels);
        }

        $lastTwo = implode('.', array_slice($labels, -2));
        $lastThree = implode('.', array_slice($labels, -3));

        return in_array($lastTwo, self::MULTI_LABEL_PUBLIC_SUFFIXES, true)
            ? $lastThree
            : $lastTwo;
    }

    protected function activeRules(Student $student, ?int $taskTemplateId = null): Collection
    {
        return BrowserPolicyRule::query()
            ->where(fn ($query) => $query
                ->where('student_id', $student->id)
                ->orWhereNull('student_id'))
            ->where(fn ($query) => $query
                ->whereNull('task_template_id')
                ->when($taskTemplateId !== null, fn ($query) => $query->orWhere('task_template_id', $taskTemplateId)))
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->orderBy('value')
            ->get();
    }

    protected function activeTaskSession(Student $student): ?TaskSession
    {
        return $student->taskSessions()
            ->where('status', 'active')
            ->latest('started_at')
            ->latest('id')
            ->first();
    }

    protected function matchingRule(Collection $rules, string $url, string $host, string $domain, string $effect, ?string $matchType = null): ?BrowserPolicyRule
    {
        return $rules
            ->where('effect', $effect)
            ->when($matchType !== null, fn (Collection $rules) => $rules->where('match_type', $matchType))
            ->first(fn (BrowserPolicyRule $rule) => $this->matchesRule($rule, $url, $host, $domain));
    }

    protected function matchesRule(BrowserPolicyRule $rule, string $url, string $host, string $domain): bool
    {
        if ($rule->match_type === 'exact_url') {
            return $this->normalizeExactUrl($url) === $this->normalizeExactUrl($rule->value);
        }

        if ($rule->match_type === 'domain_tree') {
            $value = Str::lower($rule->value);

            if ($value === '') {
                return false;
            }

            return $domain === $value
                || $host === $value
                || str_ends_with($host, '.'.$value);
        }

        return false;
    }

    protected function approvalRuleMatch(BrowserAccessRequest $accessRequest): array
    {
        if ($this->usesExactUrlRule($accessRequest->requested_url)) {
            return [
                'match_type' => 'exact_url',
                'value' => $this->normalizeExactUrl($accessRequest->requested_url),
            ];
        }

        return [
            'match_type' => 'domain_tree',
            'value' => $accessRequest->registrable_domain,
        ];
    }

    protected function usesExactUrlRule(string $url): bool
    {
        return Str::startsWith(Str::lower(trim($url)), 'file://');
    }

    protected function normalizeExactUrl(string $url): string
    {
        return trim($url);
    }

    protected function rulePayload(BrowserPolicyRule $rule): array
    {
        return [
            'id' => $rule->id,
            'effect' => $rule->effect,
            'match_type' => $rule->match_type,
            'value' => $rule->value,
            'task_template_id' => $rule->task_template_id,
            'scope' => $rule->task_template_id ? 'task_template' : ($rule->student_id ? 'student' : 'global'),
            'expires_at' => $rule->expires_at?->toAtomString(),
        ];
    }
}
