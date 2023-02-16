<?php
/**
 * Theme update notifications for critical security updates.
 */
class Bunyad_Theme_Updates
{
	protected $theme;
	protected $transient;
	
	/**
	 * In-memory update info from transient
	 *  
	 * @var array
	 */
	public $update = [];

	/**
	 * Data from latest remote check. Cached to prevent multiple requests.
	 */
	protected $remote_data = [];
	
	public function __construct()
	{
		// Set curent theme name at right hook. Legacy: get_template() can be incorrect.
		add_action('bunyad_core_post_init', function() {
			$this->theme     = Bunyad::options()->get_config('theme_name');
			$this->transient = '_' . $this->theme . '_update_theme';
		});

		add_filter('pre_set_site_transient_update_themes', [$this, 'check_update']);
		add_action('admin_init', [$this, 'admin_init']);

		add_action('init', [$this, 'fatal_check']);
		
		// As long as not AJAX, notification should either be when in wp-cron or in admin.
		if (!wp_doing_ajax()) {
			add_action(
				wp_doing_cron() ? 'init' : 'admin_init',
				[$this, 'notify_critical_site_admin']
			);
		}

		// Debug: 
		// $t = get_site_option('_site_transient_update_themes');
		// $t->last_checked = time() - (13 * HOUR_IN_SECONDS);
		// update_site_option('_site_transient_update_themes', $t);
	}
	
	/**
	 * Investigate transients to check for theme version.
	 */
	public function admin_init()
	{
		// Site transient: Shared with all network.
		$this->update = $update = get_site_transient($this->transient);
		
		if ($update && !isset($update['fatal'])) {
			
			// Already updated.
			if ($this->is_updated($update['version'])) {
				delete_site_transient($this->transient);
				return;
			}
			
			add_action('admin_notices', [$this, 'notice_critical']);
		}	
	}
	
	/**
	 * Critical security update notice for admins.
	 */
	public function notice_critical()
	{
		?>
		
		<div class="update-nag ts-update-nag">
		
			<?php if (empty($this->update['info'])): ?>
				
				<p><strong>WARNING:</strong> Your theme version requires a critical security update. Please update your theme to latest version immediately.</p>
				
			<?php else: ?>
				
				<?php echo wp_kses_post($this->update['info']); ?>
			
			<?php endif; ?>
		</div>
		
		<?php
	}

	/**
	 * Compares with current version to see if the update is already done.
	 * 
	 * @param mixed $version
	 * @return boolean
	 */
	protected function is_updated($version) 
	{
		return version_compare(Bunyad::options()->get_config('theme_version'), $version, '>=');
	}

	/**
	 * Notify site admin on a critical security update.
	 */
	public function notify_critical_site_admin()
	{
		$update = get_site_transient($this->transient);
		if (!$update || empty($update['version']) || !empty($update['notify_none'])) {
			return;
		}

		$transient_notified = "_{$this->theme}_critical_notify_{$update['version']}";

		// Already updated.
		if ($this->is_updated($update['version'])) {
			delete_site_transient($transient_notified);
			return;
		}

		// Notification done.
		if (get_site_transient($transient_notified)) {
			return;
		}

		if ($update && empty($update['safe'])) {

			$subject = sprintf(
				'[%1$s] URGENT: Theme update required for security',
				wp_specialchars_decode(get_option('blogname'), ENT_QUOTES)
			);

			$message[] = sprintf(
				'Your WordPress site requires an urgent theme update for "%1$s" to maintain security.',
				wp_get_theme()->get('Name')
			);

			$message[] = "\n" . sprintf(
				'Please login to your site admin area to learn more: %s',
				admin_url()
			);

			if (!empty($update['notify_info'])) {
				$message[] = "\n" . wp_strip_all_tags($update['notify_info']);
			}

			$message = implode("\n", $message);

			// Notify site admin about a critical security update.
			wp_mail(get_site_option('admin_email'), $subject, $message);

			// Update status.
			set_site_transient($transient_notified, 1, DAY_IN_SECONDS * 5);
		}
	}

	/**
	 * Fatal error on update.
	 */
	public function fatal_check()
	{
		$update = get_site_transient($this->transient);

		if ($update && !empty($update['fatal'])) {

			// Already updated.
			if ($this->is_updated($update['version'])) {
				delete_site_transient($this->transient);
				return;
			}

			$update['fatal'] = is_admin() ? $update['fatal_admin'] : $update['fatal'];
			wp_die(wp_kses_post($update['fatal']), '', ['response' => 503]);
		}
	}
	
	/**
	 * Checks for theme update.
	 */
	public function check_update($transient)
	{
		if (empty($transient->checked)) {
			return $transient;
		}

		$this->check_critical();
		
		return $transient;
	}
	
	/**
	 * Checks for critical theme security updates.
	 * 
	 * A secure HTTPS request is sent with data in POST to ensure version number isn't 
	 * exposed to MITM.
	 */
	public function check_critical()
	{
		/**
		 * Make a remote request if we haven't already done so. Cache remote data and
		 * check for it, as sometimes WP may update 'update_themes' transient twice, 
		 * hencing calling this method twice.
		 */
		if (!$this->remote_data) {

			$url  = 'https://system.theme-sphere.com/wp-json/api/v1/update';
			$args = [
				'body' => [
					'theme' => $this->theme,
					'ver'   => Bunyad::options()->get_config('theme_version'),

					// Checks for skin-specific critical updates too.
					'skin'  => $this->get_active_skin(),
				]
			];
			
			$api_key = Bunyad::core()->get_license();
			if (!empty($api_key)) {
				$args['headers'] = ['X-API-KEY' => $api_key];
			}

			$this->remote_data = wp_remote_post($url, $args);
		}
		
		$response = $this->remote_data;
		if (is_wp_error($response)) {
			return;
		}
		
		$body = json_decode($response['body'], true);

		// Not safe? Store in transient to add a notice later.
		if (empty($body['safe'])) {
			set_site_transient($this->transient, $body);
		}
		else {
			
			// Delete it if it's been marked safe.
			delete_site_transient($this->transient);
		}
	}

	/**
	 * Currently active skin.
	 * 
	 * @return string
	 */
	protected function get_active_skin()
	{
		$skin = Bunyad::options()->predefined_style;
		if (!$skin) {
			$skin = Bunyad::options()->installed_demo;
		} 

		return $skin ? $skin : 'default';
	}
}

// init and make available in Bunyad::get('theme_updates')
Bunyad::register('theme_updates', [
	'class' => 'Bunyad_Theme_Updates',
	'init'  => true
]);