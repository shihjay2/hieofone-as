@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/fileinput.min.css') }}">
@endsection

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">
					<div class="container-fluid panel-container">
						<div class="col-xs-6 col-md-9 text-left">
							<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">{!! $title !!}</h4>
						</div>
						<div class="col-xs-3 text-right">
							@if (isset($back))
								{!! $back !!}
							@endif
						</div>
					</div>
				</div>
                <div class="panel-body">
                    @if (isset($content))
                        {!! $content !!}
                    @endif
		                    <form id="document_upload_form" class="form-horizontal" role="form" method="POST" enctype="multipart/form-data" action="{{ $document_upload }}">
		                        {{ csrf_field() }}
		                        <label class="control-label"></label>
		                        <input id="file_input" name="file_input" type="file" multiple class="file-loading">
		                    </form>
						</div>
					</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('view.scripts')
<script src="{{ asset('assets/js/canvas-to-blob.min.js') }}"></script>
<script src="{{ asset('assets/js/sortable.min.js') }}"></script>
<script src="{{ asset('assets/js/purify.min.js') }}"></script>
<script src="{{ asset('assets/js/fileinput.min.js') }}"></script>
<script type="text/javascript">
    $(document).ready(function() {
        $('[data-toggle=offcanvas]').css('cursor', 'pointer').click(function() {
            $('.row-offcanvas').toggleClass('active');
        });
		var document_type = '{!! $document_type !!}';
        $("#file_input").fileinput({
            allowedFileExtensions: JSON.parse(document_type),
            maxFileCount: 1,
			dropZoneEnabled: false
        });
    });

	function handleError(error) {
		console.error('navigator.getUserMedia error: ', error);
	}
	const constraints = {video: true};

	(function() {
		const video = document.querySelector('video');
		const captureVideoButton = document.querySelector('#start_video');
		const screenshotButton = document.querySelector('#stop_video');
		const img = document.querySelector('#screenshot img');
		const input = document.querySelector('#img');
		const canvas = document.createElement('canvas');
		const saveButton = document.querySelector('#save_picture');
		const restartButton = document.querySelector('#restart_picture');
		const cancelButton = document.querySelector('#cancel_picture');

		function handleSuccess(stream) {
			screenshotButton.disabled = false;
			video.srcObject = stream;
		}

		captureVideoButton.onclick = function() {
			video.style.display = "block";
			navigator.mediaDevices.getUserMedia(constraints).
			then(handleSuccess).catch(handleError);
			img.style.display = "none";
		};

		restartButton.onclick = function() {
			video.style.display = "block";
			navigator.mediaDevices.getUserMedia(constraints).
			then(handleSuccess).catch(handleError);
			img.style.display = "none";
		};

		screenshotButton.onclick = function() {
			canvas.width = video.videoWidth;
			canvas.height = video.videoHeight;
			canvas.getContext('2d').drawImage(video, 0, 0);
			img.src = canvas.toDataURL('image/png');
			input.value = img.src;
			img.style.display = "block";
			saveButton.style.display = "inline";
			restartButton.style.display = "inline";
			cancelButton.style.display = "inline";
			video.style.display = "none";
		};

		cancelButton.onclick = function() {
			video.style.display = "none";
			img.style.display = "none";
			saveButton.style.display = "none";
			restartButton.style.display = "none";
		}
	})();

</script>
@endsection
