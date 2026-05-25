/**
 * Pulse Press admin application.
 */
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Flex,
	FlexItem,
	Notice,
	CheckboxControl,
	SelectControl,
	Spinner,
	TabPanel,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { postAdminAction, tabUrl } from './utils';

/**
 * @param {Array<{ id: string, name: string }>} slackChannels Channels from REST config.
 * @param {string}                                  currentValue  Saved channel ID.
 */
function buildSlackChannelSelectOptions( slackChannels, currentValue = '' ) {
	const options = [
		{ label: __( '— Select channel —', 'pulse-press' ), value: '' },
		...( slackChannels || [] ).map( ( ch ) => ( {
			label: `#${ ch.name }`,
			value: ch.id,
		} ) ),
	];
	if ( currentValue && ! options.some( ( opt ) => opt.value === currentValue ) ) {
		options.push( {
			label: sprintf(
				/* translators: %s: Slack channel ID */
				__( 'Saved channel (%s)', 'pulse-press' ),
				currentValue
			),
			value: currentValue,
		} );
	}
	return options;
}

function SlackChannelField( { config, label, value, onChange, help } ) {
	const channels = config.slackChannels || [];
	if ( config.slackConnected && channels.length > 0 ) {
		return (
			<SelectControl
				label={ label }
				value={ value }
				options={ buildSlackChannelSelectOptions( channels, value ) }
				onChange={ onChange }
				help={ help }
			/>
		);
	}
	const fallbackHelp =
		help ||
		( ! config.slackConnected
			? __( 'Connect Slack on the Connection tab to choose a channel from a list.', 'pulse-press' )
			: __( 'No channels returned for this account. Enter a channel ID manually.', 'pulse-press' ) );
	return (
		<TextControl label={ label } value={ value } onChange={ onChange } help={ fallbackHelp } />
	);
}

if ( window.pulsePressAdmin?.restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( window.pulsePressAdmin.restNonce ) );
}
if ( window.pulsePressAdmin?.restRoot ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( window.pulsePressAdmin.restRoot ) );
}

const TABS = [
	{ name: 'connection', title: __( 'Connection', 'pulse-press' ) },
	{ name: 'team', title: __( 'Team updates', 'pulse-press' ) },
	{ name: 'meeting', title: __( 'Meeting updates', 'pulse-press' ) },
	{ name: 'posts', title: __( 'Posts', 'pulse-press' ) },
	{ name: 'paste', title: __( 'Paste', 'pulse-press' ) },
	{ name: 'templates', title: __( 'Templates', 'pulse-press' ) },
	{ name: 'run', title: __( 'Run', 'pulse-press' ) },
];

function Card( { title, description, children } ) {
	return (
		<div className="pulse-press-card">
			{ title && <h2>{ title }</h2> }
			{ description && <p className="pulse-press-card__desc">{ description }</p> }
			{ children }
		</div>
	);
}

export default function App() {
	const [ config, setConfig ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ notice, setNotice ] = useState( null );
	const initialTab = window.pulsePressAdmin?.initialTab || 'connection';

	useEffect( () => {
		const params = new URLSearchParams( window.location.search );
		const n = params.get( 'pulse_press_notice' );
		if ( n === 'draft_created' ) {
			setNotice( __( 'Draft created.', 'pulse-press' ) );
		} else if ( n === 'oauth_success' ) {
			setNotice( __( 'Slack connected.', 'pulse-press' ) );
		} else if ( n === 'github_oauth_success' ) {
			setNotice( __( 'GitHub connected.', 'pulse-press' ) );
		} else if ( n === 'github_disconnected' ) {
			setNotice( __( 'GitHub disconnected.', 'pulse-press' ) );
		} else if ( n === 'github_ok' ) {
			setNotice( __( 'GitHub connection is working.', 'pulse-press' ) );
		} else if ( n === 'saved' ) {
			setNotice( __( 'Settings saved.', 'pulse-press' ) );
		} else if ( n === 'disconnected' ) {
			setNotice( __( 'Slack disconnected.', 'pulse-press' ) );
		} else if ( n === 'slack_ok' ) {
			setNotice( __( 'Slack connection is working.', 'pulse-press' ) );
		} else if ( n === 'ai_ok' ) {
			setNotice( __( 'WordPress AI is working.', 'pulse-press' ) );
		} else if ( n === 'error' || n === 'oauth_error' || n === 'github_oauth_error' ) {
			setError( decodeURIComponent( params.get( 'error_message' ) || __( 'An error occurred.', 'pulse-press' ) ) );
		}
	}, [] );

	useEffect( () => {
		apiFetch( { path: '/pulse-press/v1/config' } )
			.then( setConfig )
			.catch( ( err ) => setError( err.message || String( err ) ) );
	}, [] );

	if ( error && ! config ) {
		return (
			<div className="pulse-press-root">
				<Notice status="error" isDismissible={ false }>{ error }</Notice>
			</div>
		);
	}

	if ( ! config ) {
		return (
			<div className="pulse-press-root pulse-press-loading">
				<Spinner />
			</div>
		);
	}

	const { settings, nonces, canManage } = config;

	return (
		<div className="pulse-press-root">
			<header className="pulse-press-header">
				<h1>{ __( 'Pulse Press', 'pulse-press' ) }</h1>
				<p>{ __( 'Turn Slack, GitHub, and pasted content into formatted draft posts.', 'pulse-press' ) }</p>
			</header>

			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( null ) }>{ notice }</Notice>
			) }
			{ error && (
				<Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice>
			) }
			{ ! config.aiAvailable && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'Configure WordPress AI before creating digests.', 'pulse-press' ) }{ ' ' }
					<ExternalLink href={ config.urls.aiSettings }>{ __( 'Open AI settings', 'pulse-press' ) }</ExternalLink>
				</Notice>
			) }

			<TabPanel
				className="pulse-press-tabs"
				activeClass="is-active"
				initialTabName={ initialTab }
				onSelect={ ( name ) => {
					window.history.replaceState( null, '', tabUrl( name ) );
				} }
				tabs={ TABS }
			>
				{ ( tab ) => (
					<div className="pulse-press-panel-stack">
						{ tab.name === 'connection' && (
							<ConnectionTab config={ config } canManage={ canManage } nonces={ nonces } />
						) }
						{ tab.name === 'team' && canManage && (
							<TeamTab config={ config } nonces={ nonces } settings={ settings } />
						) }
						{ tab.name === 'meeting' && canManage && (
							<MeetingTab config={ config } settings={ settings } nonces={ nonces } />
						) }
						{ tab.name === 'posts' && canManage && (
							<PostsTab config={ config } nonces={ nonces } settings={ settings } />
						) }
						{ tab.name === 'paste' && (
							<PasteTab config={ config } nonces={ nonces } />
						) }
						{ tab.name === 'templates' && (
							<TemplatesTab config={ config } />
						) }
						{ tab.name === 'run' && canManage && (
							<RunTab config={ config } nonces={ nonces } settings={ settings } />
						) }
						{ ! canManage && [ 'team', 'meeting', 'posts', 'templates', 'run' ].includes( tab.name ) && (
							<Notice status="info" isDismissible={ false }>
								{ __( 'You need administrator access for this section.', 'pulse-press' ) }
							</Notice>
						) }
					</div>
				) }
			</TabPanel>
		</div>
	);
}

function ConnectionTab( { config, canManage, nonces } ) {
	const [ clientId, setClientId ] = useState( config.settings.slack_client_id );
	const [ clientSecret, setClientSecret ] = useState( '' );
	const [ githubClientId, setGithubClientId ] = useState( config.settings.github_client_id );
	const [ githubClientSecret, setGithubClientSecret ] = useState( '' );
	const [ githubRepos, setGithubRepos ] = useState( config.settings.github_repos || '' );

	if ( ! canManage ) {
		return (
			<Notice status="info" isDismissible={ false }>
				{ __( 'Only administrators can manage the Slack connection.', 'pulse-press' ) }
			</Notice>
		);
	}

	return (
		<>
			<Card title={ __( 'Slack connection', 'pulse-press' ) }>
				<p className={ `pulse-press-status pulse-press-status--${ config.slackConnected ? 'connected' : 'disconnected' }` }>
					{ config.slackConnected
						? `${ __( 'Connected', 'pulse-press' ) }${ config.slackUserName ? ` (${ config.slackUserName })` : '' }`
						: __( 'Not connected', 'pulse-press' ) }
				</p>
				<TextControl
					label={ __( 'Slack Client ID', 'pulse-press' ) }
					value={ clientId }
					onChange={ setClientId }
				/>
				<TextControl
					label={ __( 'Slack Client Secret', 'pulse-press' ) }
					type="password"
					value={ clientSecret }
					onChange={ setClientSecret }
					help={ config.hasSecret ? __( 'Leave blank to keep the existing secret.', 'pulse-press' ) : '' }
				/>
				{ config.slackRedirect && (
					<TextControl
						label={ __( 'OAuth redirect URL (add in Slack app)', 'pulse-press' ) }
						value={ config.slackRedirect }
						readOnly
						onFocus={ ( e ) => e.target.select() }
						help={ __( 'Copy this exact URL into Slack → OAuth & Permissions → Redirect URLs.', 'pulse-press' ) }
					/>
				) }
				<div className="pulse-press-actions">
					<Button
						variant="primary"
						onClick={ () => postAdminAction( 'save_settings', { slack_client_id: clientId, slack_client_secret: clientSecret }, nonces.save_settings ) }
					>
						{ __( 'Save credentials', 'pulse-press' ) }
					</Button>
					{ config.slackAuthorize && ! config.slackConnected && config.hasSecret && (
						<Button variant="primary" href={ config.slackAuthorize }>
							{ __( 'Connect Slack', 'pulse-press' ) }
						</Button>
					) }
					{ ! config.slackConnected && clientId && ! config.hasSecret && (
						<p className="pulse-press-card__desc">
							{ __( 'Save your Client ID and Client Secret before connecting.', 'pulse-press' ) }
						</p>
					) }
					{ config.slackConnected && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => postAdminAction( 'disconnect_slack', {}, nonces.disconnect_slack ) }
						>
							{ __( 'Disconnect', 'pulse-press' ) }
						</Button>
					) }
					<Button
						variant="secondary"
						onClick={ () => postAdminAction( 'test_slack', {}, nonces.test_slack ) }
					>
						{ __( 'Test connection', 'pulse-press' ) }
					</Button>
				</div>
			</Card>
			<Card title={ __( 'GitHub connection', 'pulse-press' ) }>
				<p className={ `pulse-press-status pulse-press-status--${ config.githubConnected ? 'connected' : 'disconnected' }` }>
					{ config.githubConnected
						? `${ __( 'Connected', 'pulse-press' ) }${ config.githubUserLogin ? ` (@${ config.githubUserLogin })` : '' }`
						: __( 'Not connected', 'pulse-press' ) }
				</p>
				<TextControl
					label={ __( 'GitHub OAuth App Client ID', 'pulse-press' ) }
					value={ githubClientId }
					onChange={ setGithubClientId }
				/>
				<TextControl
					label={ __( 'GitHub OAuth App Client Secret', 'pulse-press' ) }
					type="password"
					value={ githubClientSecret }
					onChange={ setGithubClientSecret }
					help={ config.hasGithubSecret ? __( 'Leave blank to keep the existing secret.', 'pulse-press' ) : '' }
				/>
				<TextareaControl
					label={ __( 'Repositories (owner/repo, one per line)', 'pulse-press' ) }
					value={ githubRepos }
					onChange={ setGithubRepos }
					rows={ 4 }
					help={ __( 'Example: WordPress/gutenberg', 'pulse-press' ) }
				/>
				<div className="pulse-press-actions">
					<Button
						variant="primary"
						onClick={ () =>
							postAdminAction(
								'save_settings',
								{
									github_client_id: githubClientId,
									github_client_secret: githubClientSecret,
									github_repos: githubRepos,
								},
								nonces.save_settings
							)
						}
					>
						{ __( 'Save GitHub settings', 'pulse-press' ) }
					</Button>
					{ config.githubAuthorize && ! config.githubConnected && githubClientId && (
						<Button variant="primary" href={ config.githubAuthorize }>
							{ __( 'Connect GitHub', 'pulse-press' ) }
						</Button>
					) }
					{ config.githubConnected && (
						<Button
							variant="secondary"
							isDestructive
							onClick={ () => postAdminAction( 'disconnect_github', {}, nonces.disconnect_github ) }
						>
							{ __( 'Disconnect GitHub', 'pulse-press' ) }
						</Button>
					) }
					<Button variant="secondary" onClick={ () => postAdminAction( 'test_github', {}, nonces.test_github ) }>
						{ __( 'Test GitHub', 'pulse-press' ) }
					</Button>
				</div>
			</Card>
		</>
	);
}

function TeamTab( { config, nonces, settings } ) {
	const [ channels, setChannels ] = useState( settings.team_channels || [] );
	const [ period, setPeriod ] = useState( String( settings.team_period_days ) );
	const [ frequency, setFrequency ] = useState( settings.schedule_frequency );
	const [ weekday, setWeekday ] = useState( String( settings.schedule_weekday ) );
	const [ time, setTime ] = useState( settings.schedule_time );

	const channelOptions = buildSlackChannelSelectOptions( config.slackChannels || [] ).filter(
		( opt ) => '' !== opt.value
	);

	return (
		<Card
			title={ __( 'Team updates', 'pulse-press' ) }
			description={ __( 'One combined digest draft from selected channels on a schedule or when you run manually.', 'pulse-press' ) }
		>
			{ channelOptions.length > 0 ? (
				<div className="pulse-press-channel-select">
					<label className="components-base-control__label" htmlFor="pulse-press-channels">
						{ __( 'Channels', 'pulse-press' ) }
					</label>
					<select
						id="pulse-press-channels"
						className="components-select-control__input"
						multiple
						size={ Math.min( 8, channelOptions.length ) }
						value={ channels }
						onChange={ ( e ) =>
							setChannels( Array.from( e.target.selectedOptions, ( o ) => o.value ) )
						}
					>
						{ channelOptions.map( ( opt ) => (
							<option key={ opt.value } value={ opt.value }>
								{ opt.label }
							</option>
						) ) }
					</select>
					<p className="components-base-control__help">
						{ __( 'Hold Cmd/Ctrl to select multiple channels.', 'pulse-press' ) }
					</p>
				</div>
			) : (
				<p>{ __( 'Connect Slack to select channels.', 'pulse-press' ) }</p>
			) }
			<Flex gap={ 4 } wrap>
				<FlexItem>
					<TextControl
						label={ __( 'Period (days)', 'pulse-press' ) }
						type="number"
						min={ 1 }
						max={ 90 }
						value={ period }
						onChange={ setPeriod }
					/>
				</FlexItem>
				<FlexItem>
					<SelectControl
						label={ __( 'Schedule', 'pulse-press' ) }
						value={ frequency }
						options={ [
							{ label: __( 'Daily', 'pulse-press' ), value: 'daily' },
							{ label: __( 'Weekly', 'pulse-press' ), value: 'weekly' },
						] }
						onChange={ setFrequency }
					/>
				</FlexItem>
				<FlexItem>
					<SelectControl
						label={ __( 'Weekday', 'pulse-press' ) }
						value={ weekday }
						options={ config.weekdays.map( ( d ) => ( { label: d.label, value: String( d.value ) } ) ) }
						onChange={ setWeekday }
					/>
				</FlexItem>
				<FlexItem>
					<TextControl
						label={ __( 'Time', 'pulse-press' ) }
						type="time"
						value={ time }
						onChange={ setTime }
					/>
				</FlexItem>
			</Flex>
			<div className="pulse-press-actions">
				<Button
					variant="primary"
					onClick={ () =>
						postAdminAction(
							'save_settings',
							{
								team_channels: channels,
								team_period_days: period,
								schedule_frequency: frequency,
								schedule_weekday: weekday,
								schedule_time: time,
							},
							nonces.save_settings
						)
					}
				>
					{ __( 'Save team settings', 'pulse-press' ) }
				</Button>
			</div>
		</Card>
	);
}

function MeetingBoundaryFields( { useTags, setUseTags, startTag, setStartTag, endTag, setEndTag } ) {
	return (
		<>
			<CheckboxControl
				label={ __( 'Use start and finish tags', 'pulse-press' ) }
				help={ __(
					'Only include messages from the latest start tag through the next finish tag in the lookback period. If no start tag is found, the full period is used.',
					'pulse-press'
				) }
				checked={ useTags }
				onChange={ setUseTags }
			/>
			{ useTags && (
				<>
					<TextControl
						label={ __( 'Meeting start tag', 'pulse-press' ) }
						help={ __( 'Text or emoji marker in a Slack message (e.g. meeting-start, :meeting-start:).', 'pulse-press' ) }
						value={ startTag }
						onChange={ setStartTag }
					/>
					<TextControl
						label={ __( 'Meeting finish tag', 'pulse-press' ) }
						help={ __( 'Optional. Messages after the start tag until this marker; if missing, everything after start is included.', 'pulse-press' ) }
						value={ endTag }
						onChange={ setEndTag }
					/>
				</>
			) }
		</>
	);
}

function MeetingTab( { config, settings, nonces } ) {
	const [ channel, setChannel ] = useState( settings.meeting_default_channel );
	const [ thread, setThread ] = useState( settings.meeting_thread_ts );
	const [ useTags, setUseTags ] = useState( !! settings.meeting_use_tags );
	const [ startTag, setStartTag ] = useState( settings.meeting_start_tag || '' );
	const [ endTag, setEndTag ] = useState( settings.meeting_end_tag || '' );

	return (
		<Card
			title={ __( 'Meeting updates', 'pulse-press' ) }
			description={ __( 'Defaults for fetching a channel or thread backscroll from the Run tab.', 'pulse-press' ) }
		>
			<SlackChannelField
				config={ config }
				label={ __( 'Default channel', 'pulse-press' ) }
				value={ channel }
				onChange={ setChannel }
			/>
			<TextControl label={ __( 'Default thread timestamp', 'pulse-press' ) } value={ thread } onChange={ setThread } />
			<MeetingBoundaryFields
				useTags={ useTags }
				setUseTags={ setUseTags }
				startTag={ startTag }
				setStartTag={ setStartTag }
				endTag={ endTag }
				setEndTag={ setEndTag }
			/>
			<div className="pulse-press-actions">
				<Button
					variant="primary"
					onClick={ () =>
						postAdminAction(
							'save_settings',
							{
								meeting_default_channel: channel,
								meeting_thread_ts: thread,
								meeting_use_tags: useTags ? '1' : '0',
								meeting_start_tag: startTag,
								meeting_end_tag: endTag,
							},
							nonces.save_settings
						)
					}
				>
					{ __( 'Save meeting defaults', 'pulse-press' ) }
				</Button>
			</div>
		</Card>
	);
}

function PostsTab( { config, nonces, settings } ) {
	const [ author, setAuthor ] = useState( String( settings.draft_author_id ) );
	const [ catTeam, setCatTeam ] = useState( String( settings.category_team_update ) );
	const [ catMeeting, setCatMeeting ] = useState( String( settings.category_meeting_update ) );
	const [ catRelease, setCatRelease ] = useState( String( settings.category_release_update || 0 ) );
	const [ catWhatsNew, setCatWhatsNew ] = useState( String( settings.category_whats_new_in || 0 ) );
	const [ catAgenda, setCatAgenda ] = useState( String( settings.category_agenda || 0 ) );

	const userOpts = config.users.map( ( u ) => ( { label: u.name, value: String( u.id ) } ) );
	const catOpts = [ { label: __( '— None —', 'pulse-press' ), value: '0' }, ...config.categories.map( ( c ) => ( { label: c.name, value: String( c.id ) } ) ) ];

	return (
		<Card title={ __( 'Post settings', 'pulse-press' ) }>
			<SelectControl label={ __( 'Draft author', 'pulse-press' ) } value={ author } options={ userOpts } onChange={ setAuthor } />
			<SelectControl label={ __( 'Team update category', 'pulse-press' ) } value={ catTeam } options={ catOpts } onChange={ setCatTeam } />
			<SelectControl label={ __( 'Meeting update category', 'pulse-press' ) } value={ catMeeting } options={ catOpts } onChange={ setCatMeeting } />
			<SelectControl label={ __( 'Release announcements category', 'pulse-press' ) } value={ catRelease } options={ catOpts } onChange={ setCatRelease } />
			<SelectControl label={ __( "What's new in… category", 'pulse-press' ) } value={ catWhatsNew } options={ catOpts } onChange={ setCatWhatsNew } />
			<SelectControl label={ __( 'Agenda category', 'pulse-press' ) } value={ catAgenda } options={ catOpts } onChange={ setCatAgenda } />
			<div className="pulse-press-actions">
				<Button
					variant="primary"
					onClick={ () =>
						postAdminAction(
							'save_settings',
							{
								draft_author_id: author,
								category_team_update: catTeam,
								category_meeting_update: catMeeting,
								category_release_update: catRelease,
								category_whats_new_in: catWhatsNew,
								category_agenda: catAgenda,
							},
							nonces.save_settings
						)
					}
				>
					{ __( 'Save post settings', 'pulse-press' ) }
				</Button>
			</div>
		</Card>
	);
}

function PasteTab( { config, nonces } ) {
	const [ type, setType ] = useState( 'team-update' );
	const [ content, setContent ] = useState( '' );
	const [ label, setLabel ] = useState( '' );
	const [ title, setTitle ] = useState( '' );
	const [ agendaSubtype, setAgendaSubtype ] = useState( 'dev-chat' );
	const [ product, setProduct ] = useState( 'Gutenberg' );
	const [ version, setVersion ] = useState( '' );
	const [ date, setDate ] = useState( '' );

	const typeOpts = Object.entries( config.types ).map( ( [ id, t ] ) => ( {
		label: t.label,
		value: id,
	} ) );
	const agendaOpts = Object.entries( config.agendaSubtypes || {} ).map( ( [ id, t ] ) => ( {
		label: t.label,
		value: id,
	} ) );

	const pasteFields = {
		paste_type: type,
		paste_content: content,
		paste_label: label,
		paste_title: title,
	};
	if ( type === 'agenda' ) {
		pasteFields.paste_agenda_subtype = agendaSubtype;
	}
	if ( type === 'whats-new-in' ) {
		pasteFields.paste_product = product;
		pasteFields.paste_version = version;
		pasteFields.paste_date = date;
	}

	return (
		<Card
			title={ __( 'Create draft from paste', 'pulse-press' ) }
			description={ __( 'Content is summarized with WordPress AI into block markup drafts.', 'pulse-press' ) }
		>
			<SelectControl label={ __( 'Type', 'pulse-press' ) } value={ type } options={ typeOpts } onChange={ setType } />
			{ type === 'agenda' && (
				<SelectControl
					label={ __( 'Agenda type', 'pulse-press' ) }
					value={ agendaSubtype }
					options={ agendaOpts }
					onChange={ setAgendaSubtype }
				/>
			) }
			{ type === 'whats-new-in' && (
				<Flex gap={ 4 } wrap>
					<FlexItem>
						<TextControl label={ __( 'Product', 'pulse-press' ) } value={ product } onChange={ setProduct } />
					</FlexItem>
					<FlexItem>
						<TextControl label={ __( 'Version', 'pulse-press' ) } value={ version } onChange={ setVersion } />
					</FlexItem>
					<FlexItem>
						<TextControl label={ __( 'Date label', 'pulse-press' ) } value={ date } onChange={ setDate } />
					</FlexItem>
				</Flex>
			) }
			<TextControl label={ __( 'Label (optional)', 'pulse-press' ) } value={ label } onChange={ setLabel } />
			<TextControl label={ __( 'Title override (optional)', 'pulse-press' ) } value={ title } onChange={ setTitle } />
			<TextareaControl label={ __( 'Content', 'pulse-press' ) } value={ content } onChange={ setContent } rows={ 12 } />
			<div className="pulse-press-actions">
				<Button
					variant="primary"
					disabled={ ! content.trim() || ! config.aiAvailable }
					onClick={ () => postAdminAction( 'paste_draft', pasteFields, nonces.paste_draft ) }
				>
					{ __( 'Create draft', 'pulse-press' ) }
				</Button>
			</div>
		</Card>
	);
}

function RunTab( { config, nonces, settings } ) {
	const [ channel, setChannel ] = useState( settings.meeting_default_channel );
	const [ days, setDays ] = useState( '1' );
	const [ thread, setThread ] = useState( settings.meeting_thread_ts );
	const [ label, setLabel ] = useState( '' );
	const [ useTags, setUseTags ] = useState( !! settings.meeting_use_tags );
	const [ startTag, setStartTag ] = useState( settings.meeting_start_tag || '' );
	const [ endTag, setEndTag ] = useState( settings.meeting_end_tag || '' );
	const [ releaseDays, setReleaseDays ] = useState( String( settings.release_period_days || 7 ) );

	return (
		<>
			<Card title={ __( 'Run digests', 'pulse-press' ) } description={ __( 'Same pipeline as scheduled cron.', 'pulse-press' ) }>
				<div className="pulse-press-actions">
					<Button variant="primary" onClick={ () => postAdminAction( 'run_team', {}, nonces.run_team ) }>
						{ __( 'Run team digest now', 'pulse-press' ) }
					</Button>
					<Button variant="secondary" onClick={ () => postAdminAction( 'test_ai', {}, nonces.test_ai ) }>
						{ __( 'Test WordPress AI', 'pulse-press' ) }
					</Button>
				</div>
			</Card>
			<Card title={ __( 'Run meeting digest', 'pulse-press' ) }>
				<SlackChannelField
					config={ config }
					label={ __( 'Channel', 'pulse-press' ) }
					value={ channel }
					onChange={ setChannel }
				/>
				<TextControl label={ __( 'Period (days)', 'pulse-press' ) } type="number" min={ 1 } value={ days } onChange={ setDays } />
				<TextControl label={ __( 'Thread TS (optional)', 'pulse-press' ) } value={ thread } onChange={ setThread } />
				<TextControl label={ __( 'Label (optional)', 'pulse-press' ) } value={ label } onChange={ setLabel } />
				<MeetingBoundaryFields
					useTags={ useTags }
					setUseTags={ setUseTags }
					startTag={ startTag }
					setStartTag={ setStartTag }
					endTag={ endTag }
					setEndTag={ setEndTag }
				/>
				<div className="pulse-press-actions">
					<Button
						variant="secondary"
						onClick={ () =>
							postAdminAction(
								'run_meeting',
								{
									meeting_channel: channel,
									meeting_period_days: days,
									meeting_thread_ts: thread,
									meeting_label: label,
									meeting_use_tags: useTags ? '1' : '0',
									meeting_start_tag: startTag,
									meeting_end_tag: endTag,
								},
								nonces.run_meeting
							)
						}
					>
						{ __( 'Run meeting digest', 'pulse-press' ) }
					</Button>
				</div>
			</Card>
			<Card
				title={ __( 'Run release digest', 'pulse-press' ) }
				description={ __( 'Fetches recent GitHub releases from configured repos.', 'pulse-press' ) }
			>
				<TextControl
					label={ __( 'Period (days)', 'pulse-press' ) }
					type="number"
					min={ 1 }
					value={ releaseDays }
					onChange={ setReleaseDays }
				/>
				<div className="pulse-press-actions">
					<Button
						variant="secondary"
						disabled={ ! config.githubConnected || ! config.aiAvailable }
						onClick={ () =>
							postAdminAction( 'run_release', { release_period_days: releaseDays }, nonces.run_release )
						}
					>
						{ __( 'Run release digest', 'pulse-press' ) }
					</Button>
				</div>
			</Card>
			<Card title={ __( 'Recent runs', 'pulse-press' ) }>
				{ config.runLog?.length ? (
					<table className="pulse-press-run-log">
						<thead>
							<tr>
								<th>{ __( 'Time', 'pulse-press' ) }</th>
								<th>{ __( 'Status', 'pulse-press' ) }</th>
								<th>{ __( 'Message', 'pulse-press' ) }</th>
								<th>{ __( 'Draft', 'pulse-press' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ config.runLog.map( ( entry, i ) => (
								<tr key={ i }>
									<td>{ entry.time ? new Date( entry.time * 1000 ).toLocaleString() : '' }</td>
									<td>{ entry.status }</td>
									<td>{ entry.message }</td>
									<td>
										{ entry.post_id ? (
											<a href={ `/wp-admin/post.php?post=${ entry.post_id }&action=edit` }>
												{ __( 'Edit', 'pulse-press' ) }
											</a>
										) : null }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) : (
					<p>{ __( 'No runs yet.', 'pulse-press' ) }</p>
				) }
			</Card>
		</>
	);
}

function TemplatesTab( { config } ) {
	const [ templateId, setTemplateId ] = useState( config.templateIds?.[ 0 ] || 'release-update' );
	const [ content, setContent ] = useState( '' );
	const [ previewHtml, setPreviewHtml ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const templateOpts = ( config.templateIds || [] ).map( ( id ) => ( { label: id, value: id } ) );

	useEffect( () => {
		setLoading( true );
		apiFetch( { path: '/pulse-press/v1/templates' } )
			.then( ( data ) => {
				if ( data.templates?.[ templateId ] ) {
					setContent( data.templates[ templateId ] );
				}
			} )
			.finally( () => setLoading( false ) );
	}, [ templateId ] );

	const runPreview = () => {
		setLoading( true );
		apiFetch( {
			path: `/pulse-press/v1/templates/${ templateId }/preview`,
			method: 'POST',
			data: { content },
		} )
			.then( ( res ) => setPreviewHtml( res.html || '' ) )
			.catch( ( err ) => setNotice( err.message || String( err ) ) )
			.finally( () => setLoading( false ) );
	};

	const saveTemplate = () => {
		if ( ! config.canManage ) {
			return;
		}
		setSaving( true );
		apiFetch( {
			path: '/pulse-press/v1/templates',
			method: 'POST',
			data: { id: templateId, content },
		} )
			.then( () => setNotice( __( 'Template saved.', 'pulse-press' ) ) )
			.catch( ( err ) => setNotice( err.message || String( err ) ) )
			.finally( () => setSaving( false ) );
	};

	return (
		<>
			{ notice && (
				<Notice status="success" onRemove={ () => setNotice( null ) }>{ notice }</Notice>
			) }
			<Card
				title={ __( 'Block templates', 'pulse-press' ) }
				description={ __(
					'Editable block markup with {{placeholders}} and {{#loops}}…{{/loops}}. Output matches WordPress post content.',
					'pulse-press'
				) }
			>
				<SelectControl
					label={ __( 'Template', 'pulse-press' ) }
					value={ templateId }
					options={ templateOpts }
					onChange={ setTemplateId }
				/>
				{ loading && ! content ? (
					<Spinner />
				) : (
					<TextareaControl
						label={ __( 'Block markup', 'pulse-press' ) }
						value={ content }
						onChange={ setContent }
						rows={ 16 }
						className="pulse-press-template-editor"
					/>
				) }
				<div className="pulse-press-actions">
					<Button variant="secondary" onClick={ runPreview } disabled={ loading }>
						{ __( 'Preview with sample data', 'pulse-press' ) }
					</Button>
					{ config.canManage && (
						<Button variant="primary" onClick={ saveTemplate } disabled={ saving }>
							{ __( 'Save template', 'pulse-press' ) }
						</Button>
					) }
				</div>
			</Card>
			{ previewHtml && (
				<Card title={ __( 'Preview', 'pulse-press' ) }>
					<div
						className="pulse-press-template-preview entry-content"
						dangerouslySetInnerHTML={ { __html: previewHtml } }
					/>
				</Card>
			) }
		</>
	);
}
