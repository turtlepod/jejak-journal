<?php
/**
 * Main journal template for [jejak-journal] shortcode.
 *
 * @package JejakJournal
 *
 * @var int   $current_year     Current year.
 * @var int   $current_month    Current month.
 * @var array $enabled_features Enabled feature keys.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jejak_months = array(
	1  => __( 'January', 'jejak-journal' ),
	2  => __( 'February', 'jejak-journal' ),
	3  => __( 'March', 'jejak-journal' ),
	4  => __( 'April', 'jejak-journal' ),
	5  => __( 'May', 'jejak-journal' ),
	6  => __( 'June', 'jejak-journal' ),
	7  => __( 'July', 'jejak-journal' ),
	8  => __( 'August', 'jejak-journal' ),
	9  => __( 'September', 'jejak-journal' ),
	10 => __( 'October', 'jejak-journal' ),
	11 => __( 'November', 'jejak-journal' ),
	12 => __( 'December', 'jejak-journal' ),
);

$jejak_month_name     = $jejak_months[ $current_month ] ?? '';
$jejak_journal_title  = sprintf( '%s %d', $jejak_month_name, $current_year );
$jejak_has_highlights = in_array( 'highlights', $enabled_features, true );
$jejak_has_todos      = in_array( 'todos', $enabled_features, true );
$jejak_has_journal    = in_array( 'journal', $enabled_features, true );
?>

<div class="jejak-journal-app" id="jejak-journal-app"
	data-year="<?php echo esc_attr( (string) $current_year ); ?>"
	data-month="<?php echo esc_attr( (string) $current_month ); ?>"
>

	<!-- Header: Title + Navigation -->
	<div class="jejak-header">
		<h1 class="jejak-title"><?php echo esc_html( $jejak_journal_title ); ?></h1>
		<div class="jejak-nav">
			<button type="button" class="jejak-nav-btn" id="jejak-prev-month" title="<?php esc_attr_e( 'Previous month', 'jejak-journal' ); ?>">&larr;</button>
			<button type="button" class="jejak-nav-btn jejak-nav-select" id="jejak-select-month">
				<?php esc_html_e( 'Select Month', 'jejak-journal' ); ?>
			</button>
			<button type="button" class="jejak-nav-btn" id="jejak-next-month" title="<?php esc_attr_e( 'Next month', 'jejak-journal' ); ?>">&rarr;</button>
		</div>
	</div>

	<!-- Month/Year Picker Modal -->
	<div class="jejak-month-modal" id="jejak-month-modal" style="display:none;">
		<div class="jejak-month-modal-backdrop" data-action="close-month-modal"></div>
		<div class="jejak-month-modal-content">
			<h3><?php esc_html_e( 'Select Month', 'jejak-journal' ); ?></h3>
			<div class="jejak-month-modal-fields">
				<select id="jejak-modal-year" aria-label="<?php esc_attr_e( 'Year', 'jejak-journal' ); ?>">
				</select>
				<select id="jejak-modal-month" aria-label="<?php esc_attr_e( 'Month', 'jejak-journal' ); ?>">
					<?php foreach ( $jejak_months as $jejak_m_num => $jejak_m_name ) : ?>
						<option value="<?php echo esc_attr( (string) $jejak_m_num ); ?>"><?php echo esc_html( $jejak_m_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="jejak-month-modal-actions">
				<button type="button" class="jejak-btn jejak-btn-primary" id="jejak-modal-go">
					<?php esc_html_e( 'Go', 'jejak-journal' ); ?>
				</button>
				<button type="button" class="jejak-btn jejak-btn-outline" id="jejak-modal-today">
					<?php esc_html_e( 'Today', 'jejak-journal' ); ?>
				</button>
			</div>
			<button type="button" class="jejak-btn jejak-modal-close" data-action="close-month-modal">&times;</button>
		</div>
	</div>

	<!-- Entry content (loaded via JS) -->
	<div class="jejak-content" id="jejak-content">
		<p class="jejak-loading"><?php esc_html_e( 'Loading…', 'jejak-journal' ); ?></p>
	</div>

	<!-- No entry notice (hidden by default) -->
	<div class="jejak-no-entry" id="jejak-no-entry" style="display:none;">
		<p><?php esc_html_e( 'No journal entry for this month yet.', 'jejak-journal' ); ?></p>
		<button type="button" class="jejak-btn jejak-btn-primary" id="jejak-create-entry">
			<?php esc_html_e( 'Create entry', 'jejak-journal' ); ?>
		</button>
	</div>

	<!-- Sections (hidden by default, shown after load) -->
	<div class="jejak-sections" id="jejak-sections" style="display:none;">

		<?php if ( $jejak_has_highlights ) : ?>
		<!-- Highlights Section -->
		<section class="jejak-section jejak-highlights" id="jejak-highlights-section">
			<h2 class="jejak-section-title"><?php esc_html_e( 'Highlights', 'jejak-journal' ); ?></h2>
			<div class="jejak-highlights-list" id="jejak-highlights-list"></div>
			<button type="button" class="jejak-btn jejak-btn-outline jejak-add-btn" id="jejak-add-highlight">
				+ <?php esc_html_e( 'Add highlight', 'jejak-journal' ); ?>
			</button>
		</section>
		<?php endif; ?>

		<?php if ( $jejak_has_todos ) : ?>
		<!-- ToDos Section -->
		<section class="jejak-section jejak-todos" id="jejak-todos-section">
			<div class="jejak-section-header">
				<h2 class="jejak-section-title"><?php esc_html_e( 'To-Dos', 'jejak-journal' ); ?></h2>
				<button type="button" class="jejak-btn jejak-btn-outline jejak-btn-sm" id="jejak-import-todos" style="display:none;">
					<?php esc_html_e( 'Import', 'jejak-journal' ); ?>
				</button>
			</div>
			<div class="jejak-todos-list" id="jejak-todos-list"></div>
			<button type="button" class="jejak-btn jejak-btn-outline jejak-add-btn" id="jejak-add-todo">
				+ <?php esc_html_e( 'Add to-do', 'jejak-journal' ); ?>
			</button>
		</section>
		<?php endif; ?>

		<?php if ( $jejak_has_journal ) : ?>
		<!-- Journal Section -->
		<section class="jejak-section jejak-journal-notes" id="jejak-journal-section">
			<h2 class="jejak-section-title"><?php esc_html_e( 'Journal', 'jejak-journal' ); ?></h2>
			<div class="jejak-journal-list" id="jejak-journal-tbody"></div>
		</section>
		<?php endif; ?>
	</div>

	<!-- Save notification toast -->
	<div class="jejak-saved-toast" id="jejak-saved-toast" aria-live="polite">
		<?php esc_html_e( 'Saved', 'jejak-journal' ); ?>
	</div>
</div>
