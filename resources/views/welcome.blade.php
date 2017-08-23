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
					<div class="form-group">
						<div class="col-md-6 col-md-offset-3">
							<button type="button" class="btn btn-info btn-block" data-toggle="collapse" data-target="#blockchain_detail">{{ $blockchain_count }} Blockchain Transactions</button>
						</div>
						<div class="col-md-12">
						<div id="blockchain_detail" class="collapse" style="margin-top:20px;">
							<p><strong>Last 10 transactions:</strong></p>
							{!! $blockchain_table !!}
						</div>
					</div>
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
