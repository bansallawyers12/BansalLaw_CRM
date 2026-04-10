<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Upload Document — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/crm-theme.css') }}">
    <style>
        /* documents/create — Powder Blue & Soft Gold (docs/theme.md) */
        .doc-create-page {
            min-height: 100vh;
            margin: 0;
            font-family: "Segoe UI", sans-serif;
            font-size: 14px;
            color: var(--text-dark);
            background-color: var(--page-bg);
            -webkit-font-smoothing: antialiased;
        }

        .doc-create-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .doc-create-card {
            width: 100%;
            max-width: 28rem;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(30, 61, 96, 0.06);
            padding: 1.75rem 2rem;
        }

        .doc-create-card h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            text-align: center;
            margin: 0 0 1.5rem;
        }

        .doc-create-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 0.35rem;
        }

        .doc-create-input {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            padding: 0.5rem 0.75rem;
            font-size: 14px;
            color: var(--text-dark);
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .doc-create-input:focus {
            outline: none;
            border-color: var(--sidebar-active);
            box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.15);
        }

        .doc-create-file {
            margin-top: 0.25rem;
            font-size: 14px;
            color: var(--text-dark);
        }

        .doc-create-file::file-selector-button {
            margin-right: 0.75rem;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 13px;
            border: none;
            border-radius: 8px;
            background: var(--navy);
            color: #fff;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }

        .doc-create-file::file-selector-button:hover {
            background: var(--sidebar-active);
        }

        .doc-create-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .doc-create-submit {
            width: 100%;
            padding: 0.5rem 1.5rem;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--navy);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.15s ease, box-shadow 0.15s ease;
        }

        @media (min-width: 640px) {
            .doc-create-submit {
                width: auto;
            }
        }

        .doc-create-submit:hover {
            background: var(--sidebar-active);
        }

        .doc-create-submit:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(58, 111, 168, 0.25);
        }

        .doc-create-back {
            margin-top: 1.5rem;
            text-align: center;
        }

        .doc-create-back a {
            color: var(--sidebar-active);
            font-weight: 600;
            text-decoration: none;
        }

        .doc-create-back a:hover {
            color: var(--navy);
            text-decoration: underline;
        }

        .doc-alert {
            margin-bottom: 1rem;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .doc-alert--success {
            background: rgba(30, 122, 82, 0.1);
            color: var(--success);
            border-color: rgba(30, 122, 82, 0.25);
        }

        .doc-alert--success .doc-alert-inner {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .doc-alert--success a.doc-btn-inline {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.75rem;
            padding: 0.5rem 1rem;
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            background: var(--navy);
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }

        .doc-alert--success a.doc-btn-inline:hover {
            background: var(--sidebar-active);
            color: #fff;
        }

        .doc-alert--success .doc-alert-divider {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(30, 122, 82, 0.2);
        }

        .doc-alert--error {
            background: rgba(168, 48, 32, 0.08);
            color: var(--danger);
            border-color: rgba(168, 48, 32, 0.25);
        }

        .doc-alert--error .doc-alert-inner {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .doc-alert--error ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .doc-field {
            margin-bottom: 1rem;
        }

        .doc-field-file {
            margin-bottom: 1.5rem;
        }

        .doc-alert-inner svg:first-child {
            flex-shrink: 0;
        }

        .doc-alert-grow {
            flex: 1;
            min-width: 0;
        }
    </style>
</head>
<body class="doc-create-page">
    <div class="doc-create-wrap">
        <div class="doc-create-card">
            <h1>Upload Document</h1>

            @if (session('success'))
                <div class="doc-alert doc-alert--success">
                    <div class="doc-alert-inner">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                    @if (session('show_edit_link') && session('document_id'))
                        <div class="doc-alert-divider">
                            <a href="{{ route('documents.edit', session('document_id')) }}" class="doc-btn-inline">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Place Signature Fields
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <div class="doc-alert doc-alert--error">
                    <div class="doc-alert-inner">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="doc-alert-grow">
                            <p style="font-weight: 600; margin: 0 0 0.25rem;">Upload Failed</p>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="doc-alert doc-alert--error">
                    <div class="doc-alert-inner">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div class="doc-alert-grow" style="word-break: break-word;">
                            <p style="font-weight: 600; margin: 0 0 0.25rem;">Error</p>
                            <p style="margin: 0; white-space: pre-wrap;">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="doc-field">
                    <label for="title" class="doc-create-label">Title</label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title') }}"
                        class="doc-create-input"
                        required
                    >
                </div>

                <div class="doc-field-file">
                    <label for="document" class="doc-create-label">Document (PDF)</label>
                    <input
                        type="file"
                        id="document"
                        name="document"
                        accept="application/pdf"
                        class="doc-create-file"
                        required
                    >
                </div>

                <div class="doc-create-actions">
                    <button type="submit" class="doc-create-submit">
                        Upload
                    </button>
                </div>
            </form>

            <div class="doc-create-back">
                <a href="{{ route('signatures.index') }}">Back to Documents</a>
            </div>
        </div>
    </div>
</body>
</html>
