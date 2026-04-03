<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * SmsTemplateController
 * 
 * Handles SMS template CRUD operations for AdminConsole
 */
class SmsTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * List all SMS templates
     */
    public function index(Request $request)
    {
        $templates = SmsTemplate::orderBy('title')->paginate(20);
        
        return view('AdminConsole.features.sms.templates.index', compact('templates'));
    }

    /**
     * Show create template form
     */
    public function create()
    {
        return view('AdminConsole.features.sms.templates.create');
    }

    protected const SYSTEM_CATEGORIES = ['verification', 'notification', 'manual', 'reminder'];

    /**
     * Store new template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255|unique:sms_templates,title',
            'message'     => 'required|string|max:1600',
            'description' => 'nullable|string',
            'variables'   => 'nullable|string',
            'category'    => ['nullable', 'string', \Illuminate\Validation\Rule::in(self::SYSTEM_CATEGORIES)],
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = SmsTemplate::create($request->only([
            'title', 'message', 'description', 'variables', 'category', 'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Template created successfully',
            'data' => $template,
        ]);
    }

    /**
     * Show edit template form
     */
    public function edit($id)
    {
        $template = SmsTemplate::findOrFail($id);
        
        return view('AdminConsole.features.sms.templates.edit', compact('template'));
    }

    /**
     * Update template
     */
    public function update(Request $request, $id)
    {
        $template = SmsTemplate::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255|unique:sms_templates,title,' . $id,
            'message'     => 'required|string|max:1600',
            'description' => 'nullable|string',
            'variables'   => 'nullable|string',
            'category'    => ['nullable', 'string', \Illuminate\Validation\Rule::in(self::SYSTEM_CATEGORIES)],
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $template->update($request->only([
            'title', 'message', 'description', 'variables', 'category', 'is_active',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Template updated successfully',
            'data' => $template,
        ]);
    }

    /**
     * Delete template
     */
    public function destroy($id)
    {
        $template = SmsTemplate::findOrFail($id);

        // System templates (those with an alias) are used by automated flows —
        // they can be edited or deactivated but must not be deleted.
        if (!empty($template->alias)) {
            return response()->json([
                'success' => false,
                'message' => 'System templates cannot be deleted. You may edit the message or deactivate the template instead.',
            ], 422);
        }

        if ($template->usage_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a template that has been used. Consider deactivating it instead.',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Get template by ID (API endpoint)
     */
    public function show($id)
    {
        $template = SmsTemplate::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * Get active templates (API endpoint for dropdowns)
     */
    public function active()
    {
        $templates = SmsTemplate::where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'message', 'variables', 'category']);

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }
}
