@extends('layouts.crm_client_detail')
@section('title', 'Add Matter')

@section('content')

<!-- Main Content -->
<div class="main-content adminconsole-features adminconsole-matter-form">
    <section class="section">
        <div class="section-body">
            <div class="server-error">
                @include('../Elements/flash-message')
            </div>
            <form action="{{ route('adminconsole.features.matter.store') }}" name="add-matter" autocomplete="off" enctype="multipart/form-data" method="POST">
                @csrf
                <div class="row">
                    <div class="col-12 col-md-12 col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Add Matter</h4>
                                <div class="card-header-action">
                                    <a href="{{route('adminconsole.features.matter.index')}}" class="btn btn-outline-primary"><i class="fa fa-arrow-left"></i> Back</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-3 col-md-3 col-lg-3">
                        @include('../Elements/CRM/setting')
                    </div>
                    <div class="col-9 col-md-9 col-lg-9">
                        <div class="card">
                            <div class="card-body">
                                <div id="matter-form-accordion">
                                    <div class="accordion">
                                        <div class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                                            <h4>Matter Information</h4>
                                        </div>
                                        <div class="accordion-body collapse show" id="primary_info" data-bs-parent="#matter-form-accordion">
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="title">Title <span class="span_req">*</span></label>
                                                        <input type="text" name="title" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Title" value="{{ old('title') }}">
                                                        @if ($errors->has('title'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('title') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="nick_name">Nick Name <span class="span_req">*</span></label>
                                                        <input type="text" name="nick_name" pattern="[a-zA-Z0-9 ]+" title="Only letters, numbers, and spaces are allowed" class="form-control" data-valid="required" autocomplete="off" placeholder="Enter Nick Name" value="{{ old('nick_name') }}">
                                                        @if ($errors->has('nick_name'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('nick_name') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="workflow_id">Default Workflow</label>
                                                        <select name="workflow_id" id="workflow_id" class="form-control">
                                                            <option value="">— Use General —</option>
                                                            @foreach(\App\Models\Workflow::orderBy('name')->get() as $w)
                                                            <option value="{{ $w->id }}" {{ old('workflow_id') == $w->id ? 'selected' : '' }}>{{ $w->name }}{{ $w->matter ? ' (' . $w->matter->title . ')' : '' }}</option>
                                                            @endforeach
                                                        </select>
                                                        <small class="form-text text-muted">New client matters of this type will use this workflow by default.</small>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="is_for_company">Is this matter for companies? <span class="span_req">*</span></label>
                                                        <select name="is_for_company" id="is_for_company" class="form-control" data-valid="required">
                                                            <option value="0" {{ old('is_for_company', '0') == '0' ? 'selected' : '' }}>No (For Personal Clients)</option>
                                                            <option value="1" {{ old('is_for_company') == '1' ? 'selected' : '' }}>Yes (For Company Clients Only)</option>
                                                        </select>
                                                        <small class="form-text text-muted">
                                                            If "Yes", this matter will only be available when creating matters for company clients. 
                                                            If "No", it will only be available for personal clients.
                                                        </small>
                                                        @if ($errors->has('is_for_company'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('is_for_company') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                                                <h4>Block Fee</h4>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_1_Description">Block 1 Description</label>
                                                        <input type="text" name="Block_1_Description" class="form-control" autocomplete="off" placeholder="Enter Block 1 Description" value="{{ old('Block_1_Description') }}">
                                                        @if ($errors->has('Block_1_Description'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_1_Description') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_1_Ex_Tax">Block 1 Incl. GST</label>
                                                        <input type="text" name="Block_1_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 1 Incl. GST" value="{{ old('Block_1_Ex_Tax') }}">
                                                        @if ($errors->has('Block_1_Ex_Tax'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_1_Ex_Tax') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_2_Description">Block 2 Description</label>
                                                        <input type="text" name="Block_2_Description" class="form-control" autocomplete="off" placeholder="Enter Block 2 Description" value="{{ old('Block_2_Description') }}">
                                                        @if ($errors->has('Block_2_Description'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_2_Description') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_2_Ex_Tax">Block 2 Incl. GST</label>
                                                        <input type="text" name="Block_2_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 2 Incl. GST" value="{{ old('Block_2_Ex_Tax') }}">
                                                        @if ($errors->has('Block_2_Ex_Tax'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_2_Ex_Tax') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_3_Description">Block 3 Description</label>
                                                        <input type="text" name="Block_3_Description" class="form-control" autocomplete="off" placeholder="Enter Block 3 Description" value="{{ old('Block_3_Description') }}">
                                                        @if ($errors->has('Block_3_Description'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_3_Description') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="Block_3_Ex_Tax">Block 3 Incl. GST</label>
                                                        <input type="text" name="Block_3_Ex_Tax" class="form-control" autocomplete="off" placeholder="Enter Block 3 Incl. GST" value="{{ old('Block_3_Ex_Tax') }}">
                                                        @if ($errors->has('Block_3_Ex_Tax'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('Block_3_Ex_Tax') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            
                                            <div style="margin-bottom: 15px;" class="accordion-header" role="button" data-bs-toggle="collapse" data-bs-target="#primary_info" aria-expanded="true">
                                                <h4>Additional Fee</h4>
                                            </div>
                                            <div class="row">
                                                <div class="col-12 col-md-6 col-lg-6">
                                                    <div class="form-group">
                                                        <label for="additional_fee_1">Additional Fee1</label>
                                                        <input type="text" name="additional_fee_1" class="form-control" autocomplete="off" placeholder="Enter Additional Fee" value="{{ old('additional_fee_1') }}">
                                                        @if ($errors->has('additional_fee_1'))
                                                            <span class="custom-error" role="alert">
                                                                <strong>{{ $errors->first('additional_fee_1') }}</strong>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="roles-form-actions">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Matter</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

@endsection
