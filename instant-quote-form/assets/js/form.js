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
		var picked = function ( name, fallback ) {
			var el = form.querySelector( 'input[name=' + name + ']:checked' );
			return el ? el.value : fallback;
		};
		// a service's stepper moves in steps that suit its unit: windows by 1, gutter feet by 10, square feet by 50
		var stepFor = function ( svc ) { return svc.max >= 1000 ? 50 : svc.max >= 400 ? 10 : 1; };
		var shown = totalEl.textContent.trim();
		// price digits roll in when they change
		function setTotal( text ) {
			if ( text === shown ) return;
			var still = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			var pad = Math.max( text.length, shown.length );
			var a = text.padStart( pad ), b = shown.padStart( pad );
			totalEl.textContent = '';
			for ( var i = 0; i < pad; i++ ) {
				if ( a[ i ] === ' ' ) continue;
				var d = document.createElement( 'span' );
				d.className = 'iqf-d' + ( ! still && shown && a[ i ] !== b[ i ] ? ' roll' : '' );
				d.textContent = a[ i ];
				totalEl.appendChild( d );
			}
			totalEl.setAttribute( 'aria-label', text );
			shown = text;
		}

		function update() {
			var svcKey = ( form.querySelector( 'input[name=service]:checked' ) || {} ).value;
			var svc = cfg.services[ svcKey ];
			if ( ! svc ) return;
			unitEl.textContent = 'How many ' + svc.unit;
			root.querySelectorAll( '[data-iqf-rate]' ).forEach( function ( r ) { r.hidden = r.getAttribute( 'data-iqf-rate' ) !== svcKey; } );
			root.querySelectorAll( '.iqf-extra' ).forEach( function ( l ) {
				var show = l.getAttribute( 'data-services' ).split( ' ' ).indexOf( svcKey ) !== -1;
				l.hidden = ! show;
				if ( ! show ) l.querySelector( 'input' ).checked = false;
			} );
			var qty = parseInt( form.quantity.value, 10 ) || 0;
			var lines = [];
			if ( qty < 1 || qty > svc.max ) {
				setTotal( '-' );
				linesEl.innerHTML = '<li class="iqf-hint">Enter between 1 and ' + svc.max + ' ' + svc.unit + '</li>';
				return;
			}
			var mult = parseFloat( cfg.stories[ picked( 'stories', '1' ) ] || 1 );
			var base = round( qty * parseFloat( svc.price ) * mult );
			lines.push( [ qty + ' ' + svc.unit, base ] );
			if ( base < svc.minimum ) { lines.push( [ 'Minimum visit charge', round( svc.minimum - base ) ] ); base = parseFloat( svc.minimum ); }
			var extras = 0;
			form.querySelectorAll( 'input[name="extras[]"]:checked' ).forEach( function ( c ) {
				var ex = cfg.extras[ c.value ];
				if ( ex ) { extras += parseFloat( ex.price ); lines.push( [ ex.label, parseFloat( ex.price ) ] ); }
			} );
			var subtotal = round( base + extras );
			var pct = parseFloat( cfg.discounts[ picked( 'frequency', 'once' ) ] || 0 );
			var discount = round( subtotal * pct / 100 );
			if ( discount > 0 ) lines.push( [ 'Repeat service discount (' + pct + '%)', -discount ] );
			setTotal( money( round( subtotal - discount ) ) );
			linesEl.innerHTML = '';
			lines.forEach( function ( l ) {
				var li = document.createElement( 'li' );
				var a = document.createElement( 'span' ); a.textContent = l[ 0 ];
				var b = document.createElement( 'b' ); b.textContent = money( l[ 1 ] );
				li.appendChild( a ); li.appendChild( b ); linesEl.appendChild( li );
			} );
		}
		form.querySelectorAll( '[data-iqf-step]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var svc = cfg.services[ picked( 'service', '' ) ];
				if ( ! svc ) return;
				var step = stepFor( svc ) * parseInt( btn.getAttribute( 'data-iqf-step' ), 10 );
				var next = ( parseInt( form.quantity.value, 10 ) || 0 ) + step;
				form.quantity.value = Math.max( 1, Math.min( svc.max, next ) );
				update();
			} );
		} );
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
