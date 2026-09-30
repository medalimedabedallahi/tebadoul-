<?php

namespace App\Livewire\Matches;

use App\Actions\Matching\AcceptMatch;
use App\Actions\Matching\DeclineMatch;
use App\Actions\Matching\InviteToMatch;
use App\Actions\Matching\ListMatches;
use App\Actions\Matching\ShareContact;
use App\Actions\Matching\UpdateMatchProgress;
use App\Actions\Matching\WithdrawFromMatch;
use App\Actions\Messaging\ListMatchMessages;
use App\Actions\Messaging\MarkMatchMessageRead;
use App\Actions\Messaging\SendMatchMessage;
use App\Actions\Trust\BlockUser;
use App\Actions\Trust\CreateUserReport;
use App\Actions\Trust\UnblockUser;
use App\Enums\MatchDeclineReason;
use App\Enums\MatchStatus;
use App\Enums\ReportReason;
use App\Exceptions\Matching\ContactNotAuthorizedException;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Exceptions\Trust\InteractionBlockedException;
use App\Exceptions\Trust\ReportAlreadyExistsException;
use App\Models\User;
use App\Support\Matching\MatchPresenter;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * One match of the signed-in account: the other request (compatibility data only), the score
 * and every criterion explaining it. 404 for a match the account does not take part in.
 */
class Show extends Component
{
    public string $publicId;

    public string $reason = '';

    public string $status = '';

    public ?string $actionMessage = null;

    /** @var list<string> */
    public array $actionErrors = [];

    /** @var array{email: string|null, phone: string|null}|null */
    public ?array $contact = null;

    public string $messageBody = '';

    public string $reportReason = '';

    public string $reportDetails = '';

    public function mount(string $match, ListMatches $listMatches): void
    {
        $this->publicId = $listMatches->find($this->currentUser(), $match)->public_id;
    }

    public function invite(InviteToMatch $invite): void
    {
        $this->perform(fn (User $user) => $invite->handle($user, $this->publicId), 'matches.messages.invited');
    }

    public function accept(AcceptMatch $accept): void
    {
        $this->perform(fn (User $user) => $accept->handle($user, $this->publicId), 'matches.messages.accepted');
    }

    public function decline(DeclineMatch $decline): void
    {
        $validated = Validator::make(
            ['reason' => $this->reason !== '' ? $this->reason : null],
            DeclineMatch::rules(),
            attributes: ['reason' => __('matches.fields.reason')],
        )->validate();

        $this->perform(
            fn (User $user) => $decline->handle(
                $user,
                $this->publicId,
                isset($validated['reason']) ? MatchDeclineReason::from($validated['reason']) : null,
            ),
            'matches.messages.declined',
        );
    }

    public function withdraw(WithdrawFromMatch $withdraw): void
    {
        $this->perform(fn (User $user) => $withdraw->handle($user, $this->publicId), 'matches.messages.withdrawn');
    }

    public function grantContactConsent(ShareContact $shareContact): void
    {
        $this->perform(fn (User $user) => $shareContact->grant($user, $this->publicId), 'matches.messages.consent_granted');
    }

    public function revokeContactConsent(ShareContact $shareContact): void
    {
        $this->perform(fn (User $user) => $shareContact->revoke($user, $this->publicId), 'matches.messages.consent_revoked');
    }

    public function revealContact(ShareContact $shareContact): void
    {
        $this->resetFeedback();

        try {
            $this->contact = $shareContact->reveal($this->currentUser(), $this->publicId);
            $this->actionMessage = __('matches.messages.contact_revealed');
        } catch (ContactNotAuthorizedException) {
            $this->actionErrors = [__('matches.errors.contact_not_authorized')];
        }
    }

    public function updateProgress(UpdateMatchProgress $progress): void
    {
        $validated = Validator::make(
            ['status' => $this->status],
            UpdateMatchProgress::rules(),
            attributes: ['status' => __('matches.fields.status')],
        )->validate();

        $this->perform(
            fn (User $user) => $progress->handle($user, $this->publicId, MatchStatus::from($validated['status'])),
            'matches.messages.progress_updated',
        );
    }

    public function sendMessage(SendMatchMessage $send): void
    {
        $validated = Validator::make(['body' => $this->messageBody], SendMatchMessage::rules())->validate();
        $this->perform(fn (User $user) => $send->handle($user, $this->publicId, $validated['body']), 'matches.messages.message_sent');
        $this->messageBody = '';
    }

    public function markMessageRead(string $message, MarkMatchMessageRead $markRead): void
    {
        $markRead->handle($this->currentUser(), $this->publicId, $message);
    }

    public function block(BlockUser $block): void
    {
        $this->perform(fn (User $user) => $block->handle($user, $this->publicId), 'matches.messages.blocked');
    }

    public function unblock(UnblockUser $unblock): void
    {
        $this->perform(fn (User $user) => $unblock->handle($user, $this->publicId), 'matches.messages.unblocked');
    }

    public function report(CreateUserReport $create): void
    {
        $this->resetFeedback();

        $validated = Validator::make([
            'reason' => $this->reportReason,
            'details' => $this->reportDetails !== '' ? $this->reportDetails : null,
        ], CreateUserReport::rules())->validate();

        try {
            $create->handle(
                $this->currentUser(),
                $this->publicId,
                ReportReason::from($validated['reason']),
                $validated['details'] ?? null,
            );
            $this->actionMessage = __('matches.messages.reported');
            $this->reportReason = '';
            $this->reportDetails = '';
        } catch (ReportAlreadyExistsException) {
            $this->actionErrors = [__('matches.errors.already_reported')];
        }
    }

    public function render(ListMatches $listMatches, ListMatchMessages $listMessages): View
    {
        $user = $this->currentUser();

        return view('livewire.matches.show', [
            'match' => MatchPresenter::forViewer($listMatches->find($user, $this->publicId), $user),
            'origin' => $user->professionalProfile()->with('moughataa.wilaya')->first()?->moughataa,
            'declineReasons' => collect(MatchDeclineReason::cases())
                ->mapWithKeys(fn (MatchDeclineReason $reason): array => [$reason->value => __('matches.decline_reasons.'.$reason->value)])
                ->all(),
            'reportReasons' => collect(ReportReason::cases())
                ->mapWithKeys(fn (ReportReason $reason): array => [$reason->value => __('matches.report_reasons.'.$reason->value)])
                ->all(),
            'conversation' => collect($listMessages->handle($user, $this->publicId, perPage: 50)->items())->reverse()->values(),
        ])->layout('components.layouts.app', [
            'title' => __('matches.show.title'),
        ]);
    }

    /**
     * @param  Closure(User): mixed  $operation
     */
    private function perform(Closure $operation, string $successKey): void
    {
        $this->resetFeedback();

        try {
            $operation($this->currentUser());
            $this->actionMessage = __($successKey);
            $this->contact = null;
        } catch (InvalidMatchTransitionException) {
            $this->actionErrors = [__('matches.errors.not_allowed')];
        } catch (InteractionBlockedException) {
            $this->actionErrors = [__('matches.errors.interaction_blocked')];
        }
    }

    private function resetFeedback(): void
    {
        $this->resetErrorBag();
        $this->actionMessage = null;
        $this->actionErrors = [];
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
