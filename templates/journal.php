<?php
/**
 * Main journal template for [jejak-journal] shortcode.
 *
 * @package JejakJournal
 *
 * @var int $current_year  Current year.
 * @var int $current_month Current month.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Build months dropdown options.
$months = array(
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

// Build years dropdown (current year ± 5).
$years = array();
for ( $y = $current_year - 5; $y <= $current_year + 5; $y++ ) {
	$years[] = $y;
}

$month_name = $months[ $current_month ] ?? '';
$title      = sprintf( '%s %d', $month_name, $current_year );
?>

<div class="jejak-journal-app" id="jejak-journal-app"
	data-year="<?php echo esc_attr( (string) $current_year ); ?>"
	data-month="<?php echo esc_attr( (string) $current_month ); ?>"
>
	<!-- Header: Title + Month/Year Switcher -->
	<div class="jejak-header">
		<h1 class="jejak-title"><?php echo esc_html( $title ); ?></h1>
		<div class="jejak-switcher">
			<select class="jejak-month-select" id="jejak-month-select" aria-label="<?php esc_attr_e( 'Select month', 'jejak-journal' ); ?>">
				<?php foreach ( $months as $m_num => $m_name ) : ?>
					<option value="<?php echo esc_attr( (string) $m_num ); ?>" <?php selected( $m_num, $current_month ); ?>>
						<?php echo esc_html( $m_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select class="jejak-year-select" id="jejak-year-select" aria-label="<?php esc_attr_e( 'Select year', 'jejak-journal' ); ?>">
				<?php foreach ( $years as $y ) : ?>
					<option value="<?php echo esc_attr( (string) $y ); ?>" <?php selected( $y, $current_year ); ?>>
						<?php echo esc_html( (string) $y ); ?>
					</option>
				<?php endforeach; ?>
			</select>
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

		<!-- Highlights Section -->
		<section class="jejak-section jejak-highlights" id="jejak-highlights-section">
			<h2 class="jejak-section-title"><?php esc_html_e( 'Highlights', 'jejak-journal' ); ?></h2>
			<div class="jejak-highlights-list" id="jejak-highlights-list"></div>
			<button type="button" class="jejak-btn jejak-btn-outline jejak-add-btn" id="jejak-add-highlight">
				+ <?php esc_html_e( 'Add highlight', 'jejak-journal' ); ?>
			</button>
		</section>

		<!-- ToDos Section -->
		<section class="jejak-section jejak-todos" id="jejak-todos-section">
			<h2 class="jejak-section-title"><?php esc_html_e( 'To-Dos', 'jejak-journal' ); ?></h2>
			<div class="jejak-todos-list" id="jejak-todos-list"></div>
			<button type="button" class="jejak-btn jejak-btn-outline jejak-add-btn" id="jejak-add-todo">
				+ <?php esc_html_e( 'Add to-do', 'jejak-journal' ); ?>
			</button>
		</section>

		<!-- Journal Section -->
		<section class="jejak-section jejak-journal-notes" id="jejak-journal-section">
			<h2 class="jejak-section-title"><?php esc_html_e( 'Journal', 'jejak-journal' ); ?></h2>
			<div class="jejak-journal-table-wrap">
				<table class="jejak-journal-table" id="jejak-journal-table">
					<thead>
						<tr>
							<th class="jejak-col-day"><?php esc_html_e( 'Day', 'jejak-journal' ); ?></th>
							<th class="jejak-col-date"><?php esc_html_e( 'Date', 'jejak-journal' ); ?></th>
							<th class="jejak-col-notes"><?php esc_html_e( 'Notes', 'jejak-journal' ); ?></th>
						</tr>
					</thead>
					<tbody id="jejak-journal-tbody">
					</tbody>
				</table>
			</div>
		</section>
	</div>

	<!-- Save notification toast -->
	<div class="jejak-saved-toast" id="jejak-saved-toast" aria-live="polite">
		<?php esc_html_e( 'Saved.', 'jejak-journal' ); ?>
	</div>
</div>
