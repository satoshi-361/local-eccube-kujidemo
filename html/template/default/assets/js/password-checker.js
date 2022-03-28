function ClassifyChar( ch )
	{
		if ( ( 'a' <= ch && 'z' >= ch ) || ' ' == ch )
			return 'lower';
		if ( 'A' <= ch && 'Z' >= ch )
			return 'upper';
		if ( '0' <= ch && '9' >= ch )
			return 'number';
		if ( 0 <= "`~!@#$%^&*()_-+={}|[]\\:\";',./<>?".indexOf( ch ) )
			return 'symbol';
		return 'other';
	}

	function CalcPasswordStrength( pw )
	{
		if ( !pw.length )
			return 0;
	
		var score = { "lower":26, "upper":26, "number":10, "symbol":35, "other":20 };
	
		var dist = {}, used = {};
		for ( var i = 0; i < pw.length; i++ )
			if ( undefined === used[ pw[ i ] ] )
			{	used[ pw[ i ] ] = 1;
				var c = ClassifyChar( pw[ i ] );
				if ( undefined === dist[ c ] )
					dist[ c ] = score[ c ] / 2;
				else 
					dist[ c ] = score[ c ];
			}

		var total = 0;
		$.each( dist, function( k, v ) { total += v; } );

		used = {};
		var strength = 1;
		for ( var i = 0; i < pw.length; i++ )
		{	
			if ( undefined === used[ pw[ i ] ] )
				used[ pw[ i ] ] = 1;
			else 
				used[ pw[ i ] ]++;
		
			if ( total > used[ pw[ i ] ] )
				strength *= total / used[ pw[ i ] ];
		}
	
		return parseInt( Math.log( strength ) );
	}

	function display()
	{
		var ctrl = '#passwordStrength';
		var strength = CalcPasswordStrength( $("#entry_password_first").val() );
		var percent = Math.max(15, Math.min(100, parseInt( strength )));

		$(ctrl).width( '' + percent + '%' );	
		$(ctrl).removeClass( 'progress-bar-success progress-bar-warning progress-bar-danger' );
	
		if ( 40 > strength )	
			$(ctrl).text( 'Weak' ),
			$(ctrl).addClass( 'progress-bar-danger' );
		else if ( 60 > strength )	
			$(ctrl).text( 'Good' ),
			$(ctrl).addClass( 'progress-bar-warning' );
		else if ( 90 > strength )	
			$(ctrl).text( 'Strong' ),
			$(ctrl).addClass( 'progress-bar-success' );
		else
			$(ctrl).text( 'Very Strong' ),
			$(ctrl).addClass( 'progress-bar-success' );
	
		$(ctrl).attr( 'data-strength', strength );
		$(this).attr( 'data-strength', strength );
	}

	$("#entry_password_first").keyup( function( e )
	{	
		display();
	});

	// $('button[type="submit"]').click(function( e ){
	// 	e.preventDefault();
		
	// 	var strength = CalcPasswordStrength( $("#entry_password_first").val() );
		
	// 	if ( 40 > strength )	
	// 	{	
	// 		$('.password-error').show();
	// 	}
	// 	else {
	// 		$('form[method="post"]').submit();
	// 	}
	// });
	
	// $(document).ready(function( e ){
	// 	$('.password-error').hide();
	// });