<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Waterfront\Apps\API\Compass\Requests\CreateTemplateRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateTemplateRequest;
use Waterfront\Apps\API\Compass\Resources\EmailHistory\EmailHistoryResource;
use Waterfront\Apps\API\Compass\Resources\Template\TemplateResource;
use Waterfront\Domain\Email\Actions\CreateTemplateAction;
use Waterfront\Domain\Email\Actions\FetchEmailStatusAction;
use Waterfront\Domain\Email\Actions\UpdateTemplateAction;
use Waterfront\Domain\Email\Exceptions\FailedToFetchStatusException;
use Waterfront\Domain\Email\Models\EmailHistory;
use Waterfront\Domain\Email\Models\Template;
use Waterfront\Domain\Mailer\Mailer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class EmailController
{
    public function __construct(
        private readonly CreateTemplateAction $createTemplateAction,
        private readonly UpdateTemplateAction $updateTemplateAction,
        private readonly FetchEmailStatusAction $fetchEmailStatusAction,
        private readonly Mailer $mailer,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function indexEmailHistory(Request $request, string $uuid): ResourceCollection
    {
        Assert::uuid($uuid);
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $emailHistory = EmailHistory::where('receiver_uuid', $uuid)->orderByDesc('sent_at')->paginate($pageSize);
        $emailHistory->appends('pageSize', (string) $pageSize);

        $customerEmailHistoryCount = EmailHistory::where('receiver_uuid', $uuid)->count();
        return EmailHistoryResource::collection($emailHistory)->additional([
            'meta' =>
                ['emailHistoryCount' => $customerEmailHistoryCount],
        ]);
    }

    public function showEmail(EmailHistory $emailHistory): string
    {
        return EmailHistoryResource::make($emailHistory)->toJson();
    }

    public function listTemplates(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $templates = Template::paginate($pageSize);
        $templates->appends('pageSize', (string) $pageSize);

        return TemplateResource::collection($templates);
    }

    public function showTemplate(Template $template): string
    {
        return TemplateResource::make($template)->toJson();
    }

    public function createTemplate(CreateTemplateRequest $request): Response
    {
        $hubspotTemplateId = (string) $request->string('hubspot_template_id');
        $hubspotTemplateId = $hubspotTemplateId !== '' ? $hubspotTemplateId : null;
        $slug = $request->slug;

        $this->createTemplateAction->execute($slug, $hubspotTemplateId);

        return new Response(status: ResponseAlias::HTTP_NO_CONTENT);
    }

    public function updateTemplate(UpdateTemplateRequest $request, Template $template): Response
    {
        $hubspotTemplateId = $request->hubspot_template_id;
        $this->updateTemplateAction->execute($template, $hubspotTemplateId);

        return new Response(status: ResponseAlias::HTTP_NO_CONTENT);
    }

    public function resendEmail(EmailHistory $emailHistory): Response
    {
        $this->mailer->resend($emailHistory->id);
        return new Response(status: ResponseAlias::HTTP_NO_CONTENT);
    }

    public function fetchStatus(EmailHistory $emailHistory): Response
    {
        try {
            $this->fetchEmailStatusAction->execute($emailHistory);
        } catch (FailedToFetchStatusException $exception) {
            $this->logger->error($exception->getMessage());
            return new Response(
                content: ['message' => $this->translator->translate('email-history.failed-to-fetch-status')],
                status: ResponseAlias::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        return new Response(status: ResponseAlias::HTTP_NO_CONTENT);
    }
}
