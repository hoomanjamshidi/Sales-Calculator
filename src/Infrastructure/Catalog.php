<?php
/**
 * پرس‌وجوی سبک کاتالوگ فعلی ووکامرس: دسته‌بندی‌ها و وجود محصول.
 *
 * @package SalesCalculator
 */

declare(strict_types=1);
namespace SalesCalculator\Infrastructure;

use SalesCalculator\Domain\SalesCalculator;

/**
 * گزارش از تاریخچهٔ سفارش ساخته می‌شود؛ این کلاس فقط وضعیت امروز کاتالوگ را برای فیلتر دسته و نشان «حذف‌شده» می‌خواند.
 */
final class Catalog {
	/** درگاه داده تزریق می‌شود. */
	public function __construct( private Database $database ) {}

	/** فهرست دسته‌بندی‌های محصول برای انتخاب‌گر رابط. */
	public function categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array(); }
		$list = array();
		foreach ( $terms as $term ) {
			$list[] = array(
				'id'     => (string) $term->term_id,
				'name'   => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'parent' => (string) $term->parent,
				'count'  => (int) $term->count,
			);
		}
		return $list;
	}

	/** نام دسته‌ها به ترتیب ورودی؛ دستهٔ حذف‌شده نادیده گرفته می‌شود. */
	public function categoryNames( array $ids ): array {
		$names = array();
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, 'product_cat' );
			if ( $term instanceof \WP_Term ) {
				$names[] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ); }
		}
		return $names;
	}

	/**
	 * شناسهٔ محصولات والد در دسته‌های انتخاب‌شده و همهٔ زیردسته‌هایشان.
	 *
	 * محصول حذف‌شده دیگر دسته ندارد؛ بنابراین فقط در گزارش بدون فیلتر دسته دیده می‌شود.
	 */
	public function productsInCategories( array $ids ): array {
		$terms = array();
		foreach ( $ids as $id ) {
			$terms[]  = (int) $id;
			$children = get_term_children( (int) $id, 'product_cat' );
			if ( is_array( $children ) ) {
				$terms = array_merge( $terms, array_map( 'intval', $children ) ); }
		}
		$terms = array_values( array_unique( array_filter( $terms ) ) );
		if ( ! $terms ) {
			return array(); }
		$db = $this->database->db;
		// دستهٔ ثبت‌شده هنگام نمایه‌سازی، محصولی را که امروز حذف شده هم در فیلتر نگه می‌دارد.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- شناسه‌ها عدد صحیح‌اند و نام جدول از فهرست ثابت است.
		$remembered = array_map( 'intval', (array) $db->get_col( 'SELECT DISTINCT product_id FROM ' . $this->database->table( 'categories' ) . ' WHERE term_id IN (' . implode( ',', $terms ) . ')' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- شناسه‌ها پیش‌تر به عدد صحیح تبدیل شده‌اند.
		$sql = "SELECT DISTINCT tr.object_id FROM {$db->term_relationships} tr INNER JOIN {$db->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tt.taxonomy='product_cat' AND tt.term_id IN (" . implode( ',', $terms ) . ')';
		return array_values( array_unique( array_merge( $remembered, array_map( 'intval', (array) $db->get_col( $sql ) ) ) ) );
	}

	/** ثبت دسته‌های فعلی محصولات یک سفارش؛ اجرای دوباره چیزی را تکرار نمی‌کند. */
	public function remember( array $product_ids ): void {
		$db = $this->database->db;
		foreach ( array_unique( array_map( 'intval', $product_ids ) ) as $id ) {
			if ( $id <= 0 || $id >= SalesCalculator::DETACHED_PRODUCT_BASE ) {
				continue; }
			$terms = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_array( $terms ) ) {
				continue; }
			foreach ( $terms as $term ) {
				$this->database->query( $db->prepare( 'INSERT IGNORE INTO ' . $this->database->table( 'categories' ) . ' (product_id,term_id) VALUES (%d,%d)', $id, (int) $term ) ); }
		}
	}

	/**
	 * نشان «حذف‌شده» برای ردیف‌هایی که محصول یا متغیرشان دیگر در فروشگاه نیست یا در زباله‌دان است.
	 *
	 * @param array<int,array<string,mixed>> $rows ردیف‌های نسخهٔ ثابت گزارش.
	 * @return array<int,array<string,mixed>>
	 */
	public function markDeleted( array $rows ): array {
		$ids = array();
		foreach ( $rows as $row ) {
			foreach ( array( (int) $row['product_id'], (int) ( $row['variation_id'] ?? 0 ) ) as $id ) {
				if ( $id > 0 && $id < SalesCalculator::DETACHED_PRODUCT_BASE ) {
					$ids[ $id ] = true; }
			}
		}
		$alive = array();
		if ( $ids ) {
			$db = $this->database->db;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- کلیدها عدد صحیح‌اند.
			$sql   = "SELECT ID FROM {$db->posts} WHERE ID IN (" . implode( ',', array_keys( $ids ) ) . ") AND post_type IN ('product','product_variation') AND post_status<>'trash'";
			$alive = array_flip( array_map( 'intval', (array) $db->get_col( $sql ) ) );
		}
		foreach ( $rows as &$row ) {
			$product   = (int) $row['product_id'];
			$variation = (int) ( $row['variation_id'] ?? 0 );
			// قلم دستی بدون محصول هیچ‌وقت محصول کاتالوگ نبوده است؛ «حذف‌شده» نیست.
			$detached       = $product >= SalesCalculator::DETACHED_PRODUCT_BASE;
			$row['deleted'] = ! $detached && ( ! isset( $alive[ $product ] ) || ( $variation > 0 && ! isset( $alive[ $variation ] ) ) );
		}
		return $rows;
	}
}
