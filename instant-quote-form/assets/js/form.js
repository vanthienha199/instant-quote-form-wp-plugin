/* Live estimate. Mirrors IQF_Pricing::estimate(); the server recalculates on submit. */
( function () {
	document.querySelectorAll( '.iqf[data-config]' ).forEach( function ( root ) {
		var form = root.querySelector( 'form' );
		if ( ! form ) return;
		var cfg = JSON.parse( root.getAttribute( 'data-config' ) );
		var totalEl = root.querySelector( '[data-iqf-total]' );
		var linesEl = root.querySelector( '[data-iqf-lines]' );
		var unitEl = root.querySelector( '[data-iqf-unit]' );
		var money = function ( v ) {
			var s = Math.abs( v ).toLocaleString( undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
			return ( v < 0 ? '-' : '' ) + cfg.currency + s;
		};
		var round = function ( v ) { return Math.round( v * 100 ) / 100; };

		function update() {
			var svcKey = ( form.querySelector( 'input[name=service]:checked' ) || {} ).value;
			var svc = cfg.services[ svcKey ];
			if ( ! svc ) return;
			unitEl.textContent = 'How many ' + svc.unit;
			root.querySelectorAll( '.iqf-extra' ).forEach( function ( l ) {
				var show = l.getAttribute( 'data-services' ).split( ' ' ).indexOf( svcKey ) !== -1;
				l.hidden = ! show;
				if ( ! show ) l.querySelector( 'input' ).checked = false;
			} );
			var qty = parseInt( form.quantity.value, 10 ) || 0;
			var lines = [];
			if ( qty < 1 || qty > svc.max ) {
				totalEl.textContent = '-';
				linesEl.innerHTML = '<li class="iqf-hint">Enter between 1 and ' + svc.max + ' ' + svc.unit + '</li>';
				return;
			}
			var mult = parseFloat( cfg.stories[ form.stories.value ] || 1 );
			var base = round( qty * parseFloat( svc.price ) * mult );
			lines.push( [ qty + ' ' + svc.unit, base ] );
			if ( base < svc.minimum ) { lines.push( [ 'Minimum visit charge', round( svc.minimum - base ) ] ); base = parseFloat( svc.minimum ); }
			var extras = 0;
			form.querySelectorAll( 'input[name="extras[]"]:checked' ).forEach( function ( c ) {
				var ex = cfg.extras[ c.value ];
				if ( ex ) { extras += parseFloat( ex.price ); lines.push( [ ex.label, parseFloat( ex.price ) ] ); }
			} );
			var subtotal = round( base + extras );
			var pct = parseFloat( cfg.discounts[ form.frequency.value ] || 0 );
			var discount = round( subtotal * pct / 100 );
			if ( discount > 0 ) lines.push( [ 'Repeat service discount (' + pct + '%)', -discount ] );
			totalEl.textContent = money( round( subtotal - discount ) );
			linesEl.innerHTML = '';
			lines.forEach( function ( l ) {
				var li = document.createElement( 'li' );
				var a = document.createElement( 'span' ); a.textContent = l[ 0 ];
				var b = document.createElement( 'b' ); b.textContent = money( l[ 1 ] );
				li.appendChild( a ); li.appendChild( b ); linesEl.appendChild( li );
			} );
		}
		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		update();

		form.addEventListener( 'submit', function ( e ) {
			var bad = Array.prototype.find.call( form.elements, function ( f ) { return f.willValidate && ! f.checkValidity(); } );
			if ( bad ) {
				e.preventDefault();
				bad.setAttribute( 'aria-invalid', 'true' );
				bad.focus();
				bad.reportValidity();
				return;
			}
			form.querySelector( '.iqf-submit' ).disabled = true;
		} );
	} );
} )();
