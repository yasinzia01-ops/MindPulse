/**
 * Admin quiz builder for the tabbed editor (admin/views/forms-edit.php):
 * tab switching, the Sections/Questions/Bands editors, and a handful of
 * repeatable-list widgets (tips, benefits, testimonials, social-proof
 * entries, classification labels) for the new structured funnel pages.
 * Serializes the whole thing into #mp-quiz-data-input right before submit.
 */
( function () {
	'use strict';

	var seedEl = document.getElementById( 'mp-quiz-data-seed' );
	var seed = {};
	try {
		seed = JSON.parse( seedEl.textContent || '{}' );
	} catch ( e ) {}

	function el( tag, cls, html ) {
		var node = document.createElement( tag );
		if ( cls ) node.className = cls;
		if ( html !== undefined ) node.innerHTML = html;
		return node;
	}

	function uid( prefix ) {
		return prefix + '_' + Date.now().toString( 36 ) + '_' + Math.floor( Math.random() * 1000 );
	}

	/* ---------------- Tabs ---------------- */

	var tabLinks = document.querySelectorAll( '.mp-tab-link' );
	var tabPanels = document.querySelectorAll( '.mp-tab-panel' );
	tabLinks.forEach( function ( link ) {
		link.addEventListener( 'click', function () {
			var tab = link.dataset.tab;
			tabLinks.forEach( function ( l ) { l.classList.toggle( 'is-active', l === link ); } );
			tabPanels.forEach( function ( p ) { p.classList.toggle( 'is-active', p.dataset.tab === tab ); } );
		} );
	} );

	/* ---------------- Generic repeater (list of small objects/strings) ---------------- */

	function buildRepeater( opts ) {
		var container = document.getElementById( opts.containerId );
		var fields = opts.fields; // [{ key, type, placeholder, numeric }]
		var single = !! opts.single; // true: item is a plain string in field 'text'

		function addRow( item ) {
			item = item || {};
			var row = el( 'div', 'mp-repeater-row' );

			fields.forEach( function ( f ) {
				var input = document.createElement( f.tag || 'input' );
				if ( ( f.tag || 'input' ) === 'input' ) {
					input.type = f.type || 'text';
				}
				input.className = 'mp-rep-' + f.key + ' ' + ( f.cls || 'regular-text' );
				if ( f.placeholder ) input.placeholder = f.placeholder;
				if ( 'checkbox' === f.type ) {
					input.checked = !! item[ f.key ];
				} else {
					input.value = item[ f.key ] || '';
				}
				row.appendChild( input );
			} );

			var remove = el( 'button', 'button-link mp-remove', '×' );
			remove.type = 'button';
			remove.addEventListener( 'click', function () {
				row.remove();
			} );
			row.appendChild( remove );

			container.appendChild( row );
		}

		document.getElementById( opts.addBtnId ).addEventListener( 'click', function () {
			addRow( {} );
		} );

		( opts.items || [] ).forEach( function ( item ) {
			addRow( single ? { text: item } : item );
		} );

		return {
			serialize: function () {
				return Array.prototype.map.call( container.querySelectorAll( '.mp-repeater-row' ), function ( row ) {
					var obj = {};
					fields.forEach( function ( f ) {
						var input = row.querySelector( '.mp-rep-' + f.key );
						if ( 'checkbox' === f.type ) {
							obj[ f.key ] = input.checked;
						} else if ( f.numeric ) {
							obj[ f.key ] = parseFloat( input.value ) || 0;
						} else {
							obj[ f.key ] = input.value;
						}
					} );
					return single ? obj.text : obj;
				} );
			},
		};
	}

	/* ---------------- Sections + Questions ---------------- */

	var sectionsWrap = document.getElementById( 'mp-sections' );
	var sCounter = 0;
	var qCounter = 0;

	function buildOptionRow( opt ) {
		opt = opt || {};
		var row = el( 'div', 'mp-option-row' );

		var label = el( 'input' );
		label.type = 'text';
		label.className = 'regular-text mp-opt-label';
		label.placeholder = 'Answer label';
		label.value = opt.label || '';

		var points = el( 'input' );
		points.type = 'number';
		points.className = 'small-text mp-opt-points';
		points.placeholder = 'Points';
		points.value = opt.points || 0;
		points.title = 'Points (used in Points scoring mode)';

		var correctWrap = el( 'label', 'mp-opt-correct-label' );
		var correct = document.createElement( 'input' );
		correct.type = 'checkbox';
		correct.className = 'mp-opt-correct';
		correct.checked = !! opt.is_correct;
		correctWrap.appendChild( correct );
		correctWrap.appendChild( document.createTextNode( ' Correct' ) );

		var remove = el( 'button', 'button-link mp-remove', '×' );
		remove.type = 'button';
		remove.addEventListener( 'click', function () {
			row.remove();
		} );

		row.appendChild( label );
		row.appendChild( points );
		row.appendChild( correctWrap );
		row.appendChild( remove );

		return row;
	}

	function addQuestion( questionsWrap, question ) {
		question = question || { id: uid( 'q' ), text: '', options: [ { label: '', points: 0, is_correct: false } ] };
		qCounter++;

		var card = el( 'div', 'mp-card mp-question' );
		card.dataset.qid = question.id;

		card.appendChild( el( 'div', 'mp-card__header', '<strong>Question ' + qCounter + '</strong>' ) );

		var textInput = el( 'input' );
		textInput.type = 'text';
		textInput.className = 'large-text mp-q-text';
		textInput.placeholder = 'Question text';
		textInput.value = question.text || '';
		card.appendChild( textInput );

		var optionsWrap = el( 'div', 'mp-options' );
		card.appendChild( optionsWrap );

		( question.options || [] ).forEach( function ( opt ) {
			optionsWrap.appendChild( buildOptionRow( opt ) );
		} );

		var addOptBtn = el( 'button', 'button button-small', '+ Add Option' );
		addOptBtn.type = 'button';
		addOptBtn.addEventListener( 'click', function () {
			optionsWrap.appendChild( buildOptionRow( {} ) );
		} );
		card.appendChild( addOptBtn );

		var removeBtn = el( 'button', 'button-link mp-remove', 'Remove question' );
		removeBtn.type = 'button';
		removeBtn.addEventListener( 'click', function () {
			card.remove();
		} );
		card.appendChild( removeBtn );

		questionsWrap.appendChild( card );
	}

	function addSection( section ) {
		section = section || { id: uid( 'sec' ), name: '' };
		sCounter++;

		var card = el( 'div', 'mp-card mp-section' );
		card.dataset.sectionId = section.id;

		var header = el( 'div', 'mp-card__header' );
		var nameInput = el( 'input' );
		nameInput.type = 'text';
		nameInput.className = 'regular-text mp-section-name';
		nameInput.placeholder = 'Section ' + sCounter + ' name';
		nameInput.value = section.name || '';
		header.appendChild( nameInput );
		card.appendChild( header );

		var questionsWrap = el( 'div', 'mp-section-questions' );
		card.appendChild( questionsWrap );

		( section.questions || [] ).forEach( function ( q ) {
			addQuestion( questionsWrap, q );
		} );

		var addQBtn = el( 'button', 'button', '+ Add Question' );
		addQBtn.type = 'button';
		addQBtn.addEventListener( 'click', function () {
			addQuestion( questionsWrap, null );
		} );
		card.appendChild( addQBtn );

		var removeBtn = el( 'button', 'button-link mp-remove', 'Remove section' );
		removeBtn.type = 'button';
		removeBtn.addEventListener( 'click', function () {
			card.remove();
		} );
		card.appendChild( removeBtn );

		sectionsWrap.appendChild( card );
	}

	document.getElementById( 'mp-add-section' ).addEventListener( 'click', function () {
		addSection();
	} );

	/* ---------------- Score Bands ---------------- */

	var bandsWrap = document.getElementById( 'mp-bands' );
	var bCounter = 0;

	function labeledInput( labelText, type, cls, value ) {
		var wrap = el( 'p' );
		var label = el( 'label', '', labelText + '<br/>' );
		var input = document.createElement( 'input' );
		input.type = type;
		input.className = cls + ' regular-text';
		input.value = value || ( type === 'number' ? 0 : '' );
		label.appendChild( input );
		wrap.appendChild( label );
		return wrap;
	}

	function labeledTextarea( labelText, cls, value ) {
		var wrap = el( 'p' );
		var label = el( 'label', '', labelText + '<br/>' );
		var textarea = document.createElement( 'textarea' );
		textarea.className = cls + ' large-text';
		textarea.rows = 2;
		textarea.value = value || '';
		label.appendChild( textarea );
		wrap.appendChild( label );
		return wrap;
	}

	function addBand( band ) {
		band = band || { key: uid( 'band' ), min: 0, max: 0, title: '', description: '', image: '', cta_text: '', cta_url: '' };
		bCounter++;

		var card = el( 'div', 'mp-card mp-band' );
		card.dataset.key = band.key;

		card.appendChild( el( 'div', 'mp-card__header', '<strong>Band ' + bCounter + '</strong>' ) );

		card.appendChild( labeledInput( 'Min score', 'number', 'mp-band-min', band.min ) );
		card.appendChild( labeledInput( 'Max score', 'number', 'mp-band-max', band.max ) );
		card.appendChild( labeledInput( 'Result title', 'text', 'mp-band-title', band.title ) );
		card.appendChild( labeledTextarea( 'Result description', 'mp-band-desc', band.description ) );
		card.appendChild( labeledInput( 'Image URL', 'text', 'mp-band-image', band.image ) );
		card.appendChild( labeledInput( 'CTA text', 'text', 'mp-band-cta-text', band.cta_text ) );
		card.appendChild( labeledInput( 'CTA URL', 'text', 'mp-band-cta-url', band.cta_url ) );

		var removeBtn = el( 'button', 'button-link mp-remove', 'Remove band' );
		removeBtn.type = 'button';
		removeBtn.addEventListener( 'click', function () {
			card.remove();
		} );
		card.appendChild( removeBtn );

		bandsWrap.appendChild( card );
	}

	document.getElementById( 'mp-add-band' ).addEventListener( 'click', function () {
		addBand();
	} );

	/* ---------------- Structured page repeaters ---------------- */

	var introTips = buildRepeater( {
		containerId: 'mp-intro-tips',
		addBtnId: 'mp-add-intro-tip',
		single: true,
		fields: [ { key: 'text', placeholder: 'Tip text' } ],
		items: seed.intro && seed.intro.tips,
	} );

	var previewBenefits = buildRepeater( {
		containerId: 'mp-preview-benefits',
		addBtnId: 'mp-add-preview-benefit',
		single: true,
		fields: [ { key: 'text', placeholder: 'Benefit text' } ],
		items: seed.preview && seed.preview.benefits,
	} );

	var previewTestimonials = buildRepeater( {
		containerId: 'mp-preview-testimonials',
		addBtnId: 'mp-add-preview-testimonial',
		fields: [
			{ key: 'name', placeholder: 'Name' },
			{ key: 'text', tag: 'textarea', placeholder: 'Testimonial text' },
		],
		items: seed.preview && seed.preview.testimonials,
	} );

	var previewSocialProof = buildRepeater( {
		containerId: 'mp-preview-social-proof',
		addBtnId: 'mp-add-preview-social-proof',
		fields: [
			{ key: 'name', placeholder: 'Name' },
			{ key: 'flag', placeholder: 'Flag emoji (optional)', cls: 'small-text' },
			{ key: 'detail', placeholder: 'e.g. an IQ score of 128' },
		],
		items: seed.preview && seed.preview.social_proof_items,
	} );

	var checkoutBenefits = buildRepeater( {
		containerId: 'mp-checkout-benefits',
		addBtnId: 'mp-add-checkout-benefit',
		single: true,
		fields: [ { key: 'text', placeholder: 'Benefit text' } ],
		items: seed.checkout && seed.checkout.benefits,
	} );

	var reportClassifications = buildRepeater( {
		containerId: 'mp-report-classifications',
		addBtnId: 'mp-add-report-classification',
		fields: [
			{ key: 'min', type: 'number', numeric: true, cls: 'small-text', placeholder: 'Min' },
			{ key: 'max', type: 'number', numeric: true, cls: 'small-text', placeholder: 'Max' },
			{ key: 'label', placeholder: 'Label, e.g. Superior' },
		],
		items: seed.report && seed.report.classification_labels,
	} );

	/* ---------------- Serialize on submit ---------------- */

	function val( id ) {
		var node = document.getElementById( id );
		return node ? node.value : '';
	}

	function checked( id ) {
		var node = document.getElementById( id );
		return !! ( node && node.checked );
	}

	function serialize() {
		var sections = [];
		var questions = [];

		Array.prototype.forEach.call( sectionsWrap.querySelectorAll( '.mp-section' ), function ( sCard ) {
			sections.push( {
				id: sCard.dataset.sectionId,
				name: sCard.querySelector( '.mp-section-name' ).value,
			} );

			Array.prototype.forEach.call( sCard.querySelectorAll( '.mp-question' ), function ( qCard ) {
				var options = Array.prototype.map.call( qCard.querySelectorAll( '.mp-option-row' ), function ( row ) {
					return {
						label: row.querySelector( '.mp-opt-label' ).value,
						points: parseInt( row.querySelector( '.mp-opt-points' ).value, 10 ) || 0,
						is_correct: row.querySelector( '.mp-opt-correct' ).checked,
					};
				} );

				questions.push( {
					id: qCard.dataset.qid,
					section_id: sCard.dataset.sectionId,
					text: qCard.querySelector( '.mp-q-text' ).value,
					options: options,
				} );
			} );
		} );

		var bands = Array.prototype.map.call( bandsWrap.querySelectorAll( '.mp-band' ), function ( card ) {
			return {
				key: card.dataset.key,
				min: parseInt( card.querySelector( '.mp-band-min' ).value, 10 ) || 0,
				max: parseInt( card.querySelector( '.mp-band-max' ).value, 10 ) || 0,
				title: card.querySelector( '.mp-band-title' ).value,
				description: card.querySelector( '.mp-band-desc' ).value,
				image: card.querySelector( '.mp-band-image' ).value,
				cta_text: card.querySelector( '.mp-band-cta-text' ).value,
				cta_url: card.querySelector( '.mp-band-cta-url' ).value,
			};
		} );

		var settings = {
			lead_capture_before: !! document.querySelector( '[name="settings_lead_capture_before"]' ).checked,
			is_premium: checked( 'settings_is_premium' ),
			price: parseFloat( document.querySelector( '[name="settings_price"]' ).value ) || 0,
			currency: document.querySelector( '[name="settings_currency"]' ).value || 'usd',
			thank_you_message: val( 'settings_thank_you' ),
		};

		return {
			sections: sections,
			questions: questions,
			bands: bands,
			scoring_mode: val( 'scoring_mode' ) || 'points',
			settings: settings,
			intro: {
				enabled: checked( 'intro_enabled' ),
				logo_url: val( 'intro_logo_url' ),
				title: val( 'intro_title' ),
				subtitle: val( 'intro_subtitle' ),
				meta_text: val( 'intro_meta_text' ),
				button_text: val( 'intro_button_text' ),
				tips: introTips.serialize(),
			},
			processing: {
				enabled: checked( 'processing_enabled' ),
				title: val( 'processing_title' ),
				subtitle: val( 'processing_subtitle' ),
				duration_seconds: parseInt( val( 'processing_duration_seconds' ), 10 ) || 4,
			},
			preview: {
				enabled: checked( 'preview_enabled' ),
				headline: val( 'preview_headline' ),
				subheadline: val( 'preview_subheadline' ),
				locked_label: val( 'preview_locked_label' ),
				button_text: val( 'preview_button_text' ),
				guarantee_title: val( 'preview_guarantee_title' ),
				guarantee_text: val( 'preview_guarantee_text' ),
				benefits: previewBenefits.serialize(),
				testimonials: previewTestimonials.serialize(),
				social_proof_enabled: checked( 'preview_social_proof_enabled' ),
				social_proof_items: previewSocialProof.serialize(),
			},
			email_capture: {
				enabled: checked( 'email_capture_enabled' ),
				headline: val( 'email_capture_headline' ),
				subheadline: val( 'email_capture_subheadline' ),
				stat1_label: val( 'email_capture_stat1_label' ),
				stat1_value: val( 'email_capture_stat1_value' ),
				stat2_label: val( 'email_capture_stat2_label' ),
				stat2_value: val( 'email_capture_stat2_value' ),
				button_text: val( 'email_capture_button_text' ),
				trust_text: val( 'email_capture_trust_text' ),
			},
			checkout: {
				headline: val( 'checkout_headline' ),
				price_caption: val( 'checkout_price_caption' ),
				benefits: checkoutBenefits.serialize(),
			},
			report: {
				hero_title: val( 'report_hero_title' ),
				hero_subtitle: val( 'report_hero_subtitle' ),
				show_iq_style: checked( 'report_show_iq_style' ),
				certificate_enabled: checked( 'report_certificate_enabled' ),
				certificate_title: val( 'report_certificate_title' ),
				disclaimer: val( 'report_disclaimer' ),
				classification_labels: reportClassifications.serialize(),
			},
			custom_css: val( 'custom_css' ),
		};
	}

	var form = document.getElementById( 'mp-quiz-form' );
	form.addEventListener( 'submit', function () {
		document.getElementById( 'mp-quiz-data-input' ).value = JSON.stringify( serialize() );
	} );

	/* ---------------- Init from seed ---------------- */

	// PHP stores sections as flat {id, name} metadata and questions as a
	// flat list carrying section_id; group them back up here so each
	// section's card starts pre-populated with its own questions.
	var questionsBySection = {};
	( seed.questions || [] ).forEach( function ( q ) {
		var sid = q.section_id || 'default';
		questionsBySection[ sid ] = questionsBySection[ sid ] || [];
		questionsBySection[ sid ].push( q );
	} );

	if ( seed.sections && seed.sections.length ) {
		seed.sections.forEach( function ( section ) {
			addSection( {
				id: section.id,
				name: section.name,
				questions: questionsBySection[ section.id ] || [],
			} );
		} );
	} else {
		addSection( { id: uid( 'sec' ), name: 'Quiz', questions: seed.questions || [] } );
	}
	if ( ! sectionsWrap.querySelector( '.mp-question' ) ) {
		addQuestion( sectionsWrap.querySelector( '.mp-section-questions' ), null );
	}

	( seed.bands || [] ).forEach( addBand );
	if ( ! seed.bands || ! seed.bands.length ) {
		addBand();
	}
} )();
