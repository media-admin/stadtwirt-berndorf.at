<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\Company;
use FluentCrm\App\Services\Helper;
use FluentCrm\Framework\Http\Request\Request;
use FluentCrm\Framework\Support\Str;
use FluentCrm\App\Models\Webhook;
use FluentCrm\App\Models\Lists;
use FluentCrm\App\Models\Tag;

/**
 *  WebhookController - REST API Handler Class
 *
 *  REST API Handler
 *
 * @package FluentCrm\App\Http
 *
 * @version 1.0.0
 */
class WebhookController extends Controller
{
    public function index(Request $request, Webhook $webhook)
    {
        $fields = $webhook->getFields();
        $search = $request->getSafe('search');

        $webhooks = $webhook->latest()->get()->toArray();

        if (!empty($search)) {
            $search = strtolower($search);
            $webhooks = array_map(function ($row) use ($search) {
                $value = isset($row['value']) && is_array($row['value']) ? $row['value'] : [];
                $name = strtolower((string)($value['name'] ?? ''));

                if ($name !== '' && Str::contains($name, $search)) {
                    return $row;
                }
                return null;
            }, $webhooks);
        }

        $rows = [];
        foreach ($webhooks as $row) {
            if ($row) {
                $rows[] = $row;
            }
        }


        $response = [
            'webhooks'      => $rows,
            'fields'        => $fields['fields'],
            'custom_fields' => $fields['custom_fields'],
            'lists'         => Lists::get(),
            'tags'          => Tag::get()
        ];

        if (Helper::isCompanyEnabled()) {
            $response['companies'] = Company::get();
        }

        return $response;
    }

    public function create(Request $request, Webhook $webhook)
    {
        $data = $request->all();

        $validatedData = $this->validate($data, [
            'name'   => 'required',
            'status' => 'required'
        ]);

        $webhook = $webhook->store($validatedData);

        return [
            'id'       => $webhook->id,
            'webhook'  => $webhook->value,
            'webhooks' => $webhook->latest()->get(),
            'message'  => __('Successfully created the WebHook', 'fluent-crm')
        ];
    }

    public function update(Request $request, Webhook $webhook, $id)
    {
        $existingWebhook = $webhook->find($id);

        if (!$existingWebhook) {
            return $this->sendError([
                'message' => __('Webhook not found', 'fluent-crm')
            ], 404);
        }

        $existingWebhook->saveChanges($request->all());

        return [
            'webhooks' => $webhook->latest()->get(),
            'message'  => __('Successfully updated the webhook', 'fluent-crm')
        ];
    }

    public function delete(Webhook $webhook, $id)
    {
        $webhook->where('id', $id)->delete();

        return [
            'webhooks' => $webhook->latest()->get(),
            'message'  => __('Successfully deleted the webhook', 'fluent-crm')
        ];
    }
}
