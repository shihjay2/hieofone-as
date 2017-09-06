@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row">
		<div class="col-md-10 col-md-offset-1">
			<div class="panel panel-default">
				<div class="panel-heading">Welcome</div>

				<div class="panel-body">
					<p>Welome to the HIE of One Authorization Server for {{ $name }}.</p>
					<p><a href="https://github.com/shihjay2/hieofone-as/wiki" target="_blank">Click here</a> more information about how to interface with this User Managed Access Server.</p>
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
