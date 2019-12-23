@extends('layouts.app')

@section('view.stylesheet')
	<link rel="stylesheet" href="{{ asset('assets/css/bootstrap-select.min.css') }}">
@endsection

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-8 col-md-offset-2">
			<div class="panel panel-default">
				<div class="panel-heading">{{ $title }}</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" action="{{ $action }}">
						{{ csrf_field() }}

						<div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
							<label for="name" class="col-md-4 control-label">Policy Name</label>

							<div class="col-md-6">
								<input id="name" class="form-control" name="name" value="{{ old('name', $name_value) }}">

								@if ($errors->has('name'))
									<span class="help-block">
										<strong>{{ $errors->first('name') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('type') ? ' has-error' : '' }}">
							<label for="type" class="col-md-4 control-label">Scope Type</label>

							<div class="col-md-6">
								<select class="form-control selectpicker" id="type" name="type" value="{{ old('type') }}" multiple>
									{!! $type !!}
								</select>

								@if ($errors->has('type'))
									<span class="help-block">
										<strong>{{ $errors->first('type') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('parameter') ? ' has-error' : '' }}">
							<label for="parameter" class="col-md-4 control-label">Scopes</label>

							<div class="col-md-6">
								<select class="form-control selectpicker" id="parameter" name="parameter[]" multiple="multiple">
									{!! $parameter !!}
								</select>

								@if ($errors->has('parameter'))
									<span class="help-block">
										<strong>{{ $errors->first('parameter') }}</strong>
									</span>
								@endif
							</div>
						</div>

						<div class="form-group{{ $errors->has('fhir_scope') ? ' has-error' : '' }}">
							<label for="fhir_scope" class="col-md-4 control-label">FHIR Scopes</label>

							<div class="col-md-6">
								<select class="form-control selectpicker" id="fhir_scope" name="fhir_scope[]" multiple="multiple">
									{!! $fhir !!}
								</select>

								@if ($errors->has('fhir_scope'))
									<span class="help-block">
										<strong>{{ $errors->first('fhir_scope') }}</strong>
									</span>
								@endif
							</div>
						</div>

						@if (isset($user_policies))
							{!! $user_policies !!}
						@endif

						@if (isset($rs))
							{!! $rs !!}
						@endif

						<div class="form-group">
							<div class="col-md-6 col-md-offset-4">
								<button type="submit" class="btn btn-success btn-block" name="submit" value="save">
									<i class="fa fa-btn fa-check"></i> Save
								</button>
								<button type="submit" class="btn btn-warning btn-block" name="submit" value="cancel">
									<i class="fa fa-btn fa-times"></i> Cancel
								</button>
								@if (isset($edit))
									<button type="submit" class="btn btn-danger btn-block" name="submit" value="delete">
										<i class="fa fa-btn fa-trash"></i> Delete
									</button>
								@endif
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
<script src="{{ asset('assets/js/bootstrap-select.min.js') }}"></script>
<script type="text/javascript">
	$(document).ready(function() {
	});
</script>
@endsection
