@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
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
					@if (isset($message_action))
					  <div class="alert alert-success">
						<strong>{{ $message_action }}</strong>
					  </div>
					@endif
					{!! $content !!}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
	$(document).ready(function() {
		$("#remove_permissions_button").on('click', function() {
			return confirm('Removing all permissions cannot be undone!');
		});
	});
</script>
@endsection
