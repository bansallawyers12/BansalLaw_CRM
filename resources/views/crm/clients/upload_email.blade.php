<form method="post" action="{{URL::to('/upload-email')}}" name="uploadmail"  autocomplete="off" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="client_id" id="maclient_id">
        <div class="row">


            <div class="form-group">
                <label for="email_file">Upload Outlook Email (.msg)</label>
                <input type="file" name="email_file" class="form-control-file" accept=".msg" required>
            </div>

            <div class="col-4 col-md-4 col-lg-4">
                <div class="form-group">
                    <button onclick="customValidate('uploadmail')" class="btn btn-info" type="submit">Create</button>
                </div>
            </div>
        </div>
    </form>
