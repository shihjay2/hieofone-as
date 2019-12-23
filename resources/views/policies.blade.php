@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h4 class="panel-title" style="height:35px;display:table-cell !important;vertical-align:middle;">Resource Registration Consent Default Policies</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" action="{{ URL::to('change_policy') }}">
						<div style="text-align: center;">
							{!! $content !!}
						</div>
						{!! $policies !!}
						<div class="form-group">
							<div class="col-md-6 col-md-offset-3">
								<button type="submit" class="btn btn-success btn-block" name="submit" value="save">
									<i class="fa fa-btn fa-check"></i> Save
								</button>
								<button type="submit" class="btn btn-danger btn-block" name="submit" value="cancel">
									<i class="fa fa-btn fa-times"></i> Cancel
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('view.scripts')
<script type="text/javascript">
	$(document).ready(function() {
	});
</script>
@endsection
