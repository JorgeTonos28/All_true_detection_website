<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Str;

class ApiDocumentationController extends Controller
{
    public function index()
    {
        $source = file_get_contents(base_path('docs/api.md'));
        $html = Str::markdown($source, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $contents = [];
        $html = preg_replace_callback('/<h2>(.*?)<\/h2>/s', function ($match) use (&$contents) {
            $title = strip_tags($match[1]);
            $id = Str::slug($title);
            $contents[$id] = $title;

            return '<h2 id="'.e($id).'">'.$match[1].'</h2>';
        }, $html);
        $contract = json_decode(file_get_contents(base_path('packages/contracts/openapi.json')), true, 512, JSON_THROW_ON_ERROR);

        return response()->view('api.documentation', ['html' => $html, 'contents' => $contents, 'contract' => $contract, 'settings' => Setting::values()]);
    }

    public function contract()
    {
        return response()->file(base_path('packages/contracts/openapi.json'), ['Content-Type' => 'application/json', 'Content-Disposition' => 'attachment; filename="atd-api-v1.json"']);
    }
}
