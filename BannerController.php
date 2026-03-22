<?php
/**
 * Banner Controller - Complete CRUD Operations
 * BannerController.php
 */

class BannerController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * CREATE - Insert a new banner
     */
    public function createBanner($data) {
        try {
            $this->db->beginTransaction();
            
            // Insert into banners table
            $sql = "INSERT INTO banners (
                group_name, title, subtitle, description, 
                section, layout, desktop_image, mobile_image, text_color,
                is_active, show_desktop, show_tablet, show_mobile, priority
            ) VALUES (
                :group_name, :title, :subtitle, :description,
                :section, :layout, :desktop_image, :mobile_image, :text_color,
                :is_active, :show_desktop, :show_tablet, :show_mobile, :priority
            )";
            
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':group_name' => $data['group'] ?? null,
                ':title' => $data['title'] ?? null,
                ':subtitle' => $data['subtitle'] ?? null,
                ':description' => $data['description'] ?? null,
                ':section' => $data['section'] ?? 'top',
                ':layout' => $data['layout'] ?? 'cols-1',
                ':desktop_image' => $data['desktopImage'] ?? null,
                ':mobile_image' => $data['mobileImage'] ?? null,
                ':text_color' => $data['textColor'] ?? '#ffffff',
                ':is_active' => $data['status']['active'] ?? true,
                ':show_desktop' => $data['status']['showDesktop'] ?? true,
                ':show_tablet' => $data['status']['showTablet'] ?? true,
                ':show_mobile' => $data['status']['showMobile'] ?? true,
                ':priority' => $data['status']['priority'] ?? 1
            ]);
            
            $bannerId = $this->db->lastInsertId();
            
            // Insert related data
            if (isset($data['background'])) {
                $this->insertBackground($bannerId, $data['background']);
            }
            
            if (isset($data['ctas']) && is_array($data['ctas'])) {
                $this->insertCTAs($bannerId, $data['ctas']);
            }
            
            if (isset($data['styling'])) {
                $this->insertStyling($bannerId, $data['styling']);
            }
            
            if (isset($data['badge'])) {
                $this->insertBadge($bannerId, $data['badge']);
            }
            
            if (isset($data['animations'])) {
                $this->insertAnimations($bannerId, $data['animations']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id' => $bannerId,
                'message' => 'Banner created successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create banner: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * READ - Get all banners
     */
    public function getAllBanners() {
        $sql = "SELECT * FROM banner_full_view ORDER BY group_name, priority, section";
        $stmt = $this->db->query($sql);
        $banners = $stmt->fetchAll();
        
        foreach ($banners as &$banner) {
            // Use 'id' to get CTAs, not 'banner_id'
            $banner['ctas'] = $this->getBannerCTAs($banner['banner_id']);
        }
        
        return $this->formatBannersForFrontend($banners);
    }
    
    /**
     * READ - Get banner by ID
     */
    public function getBannerById($id) {
        $sql = "SELECT * FROM banner_full_view WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $banner = $stmt->fetch();
        
        if ($banner) {
            $banner['ctas'] = $this->getBannerCTAs($id);
            return $this->formatBannerForFrontend($banner);
        }
        
        return null;
    }
    
    /**
     * READ - Get banners by section
     */
    public function getBannersBySection($section) {
        $sql = "SELECT * FROM banner_full_view WHERE section = :section AND is_active = 1 ORDER BY priority";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':section' => $section]);
        $banners = $stmt->fetchAll();
        
        foreach ($banners as &$banner) {
            $banner['ctas'] = $this->getBannerCTAs($banner['id']);
        }
        
        return $this->formatBannersForFrontend($banners);
    }
    
    /**
     * READ - Get banners by group
     */
    public function getBannersByGroup($group) {
        $sql = "SELECT * FROM banner_full_view WHERE group_name = :group ORDER BY priority, section";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':group' => $group]);
        $banners = $stmt->fetchAll();
        
        foreach ($banners as &$banner) {
            $banner['ctas'] = $this->getBannerCTAs($banner['id']);
        }
        
        return $this->formatBannersForFrontend($banners);
    }
    
    /**
     * READ - Get active banners
     */
    public function getActiveBanners() {
        $sql = "SELECT * FROM banner_full_view WHERE is_active = 1 ORDER BY group_name, priority, section";
        $stmt = $this->db->query($sql);
        $banners = $stmt->fetchAll();
        
        foreach ($banners as &$banner) {
            $banner['ctas'] = $this->getBannerCTAs($banner['id']);
        }
        
        return $this->formatBannersForFrontend($banners);
    }
    
    /**
     * UPDATE - Update existing banner
     */public function updateBanner($id, $data) {
    try {
        $this->db->beginTransaction();
        
        // First get banner_id for child table updates
        $sql = "SELECT banner_id FROM banners WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        
        if (!$row) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Banner not found'];
        }
        
        $bannerId = $row['banner_id'];
        
        // Update main banner table using id
        $sql = "UPDATE banners SET
            group_name = :group_name,
            title = :title,
            subtitle = :subtitle,
            description = :description,
            section = :section,
            layout = :layout,
            desktop_image = :desktop_image,
            mobile_image = :mobile_image,
            text_color = :text_color,
            is_active = :is_active,
            show_desktop = :show_desktop,
            show_tablet = :show_tablet,
            show_mobile = :show_mobile,
            priority = :priority,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";  // ← Use id, not banner_id
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,  // ← Use id from parameter
            ':group_name' => $data['group'] ?? null,
            ':title' => $data['title'] ?? null,
            ':subtitle' => $data['subtitle'] ?? null,
            ':description' => $data['description'] ?? null,
            ':section' => $data['section'] ?? 'top',
            ':layout' => $data['layout'] ?? 'cols-1',
            ':desktop_image' => $data['desktopImage'] ?? null,
            ':mobile_image' => $data['mobileImage'] ?? null,
            ':text_color' => $data['textColor'] ?? '#ffffff',
            ':is_active' => $data['status']['active'] ?? true,
            ':show_desktop' => $data['status']['showDesktop'] ?? true,
            ':show_tablet' => $data['status']['showTablet'] ?? true,
            ':show_mobile' => $data['status']['showMobile'] ?? true,
            ':priority' => $data['status']['priority'] ?? 1
        ]);
        
        // Delete and re-insert related data using banner_id
        $this->deleteRelatedData($bannerId);
        
        if (isset($data['background'])) {
            $this->insertBackground($bannerId, $data['background']);
        }
        
        if (isset($data['ctas']) && is_array($data['ctas'])) {
            $this->insertCTAs($bannerId, $data['ctas']);
        }
        
        if (isset($data['styling'])) {
            $this->insertStyling($bannerId, $data['styling']);
        }
        
        if (isset($data['badge'])) {
            $this->insertBadge($bannerId, $data['badge']);
        }
        
        if (isset($data['animations'])) {
            $this->insertAnimations($bannerId, $data['animations']);
        }
        
        $this->db->commit();
        
        return [
            'success' => true,
            'message' => 'Banner updated successfully'
        ];
        
    } catch (Exception $e) {
        $this->db->rollBack();
        return [
            'success' => false,
            'message' => 'Failed to update banner: ' . $e->getMessage()
        ];
    }
}
    
    /**
     * UPDATE - Update banner status only
     */
    public function updateBannerStatus($id, $status) {
        try {
            $sql = "UPDATE banners SET is_active = :status WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $id, ':status' => $status]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * UPDATE - Update banner priority
     */
    public function updateBannerPriority($id, $priority) {
        try {
            $sql = "UPDATE banners SET priority = :priority WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $id, ':priority' => $priority]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * DELETE - Delete banner by ID
     */
    // REPLACE this method in BannerController.php

/**
 * DELETE - Delete banner by ID
 */
public function deleteBanner($id) {
    try {
        $this->db->beginTransaction();
        
        // First, get the banner_id for child table cleanup
        $sql = "SELECT banner_id FROM banners WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        
        if (!$row) {
            $this->db->rollBack();
            return false;
        }
        
        $bannerId = $row['banner_id'];
        
        // Delete child records using banner_id
        $this->deleteRelatedData($bannerId);
        
        // Delete parent record using id
        $sql = "DELETE FROM banners WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $id]);
        
        $this->db->commit();
        return $result;
        
    } catch (Exception $e) {
        $this->db->rollBack();
        error_log("Delete failed: " . $e->getMessage());
        return false;
    }
}
    
    /**
     * DELETE - Delete all banners
     */
    public function deleteAllBanners() {
        try {
            $this->db->beginTransaction();
            
            $this->db->exec("DELETE FROM banner_animations");
            $this->db->exec("DELETE FROM banner_badges");
            $this->db->exec("DELETE FROM banner_styling");
            $this->db->exec("DELETE FROM banner_ctas");
            $this->db->exec("DELETE FROM banner_backgrounds");
            $this->db->exec("DELETE FROM banners");
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Bulk create banners
     */
    public function bulkCreateBanners($banners) {
        $results = [];
        foreach ($banners as $banner) {
            $results[] = $this->createBanner($banner);
        }
        return $results;
    }
    
    /**
     * Helper: Insert background data
     */
    private function insertBackground($bannerId, $background) {
        $sql = "INSERT INTO banner_backgrounds (
            banner_id, bg_type, bg_color, gradient_color1, 
            gradient_color2, gradient_angle, bg_image
        ) VALUES (
            :banner_id, :bg_type, :bg_color, :gradient_color1,
            :gradient_color2, :gradient_angle, :bg_image
        )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':banner_id' => $bannerId,
            ':bg_type' => $background['type'] ?? 'solid',
            ':bg_color' => $background['color'] ?? null,
            ':gradient_color1' => $background['gradient']['color1'] ?? null,
            ':gradient_color2' => $background['gradient']['color2'] ?? null,
            ':gradient_angle' => $background['gradient']['angle'] ?? 135,
            ':bg_image' => null
        ]);
    }
    
    /**
     * Helper: Insert CTAs
     */
    private function insertCTAs($bannerId, $ctas) {
        $sql = "INSERT INTO banner_ctas (
            banner_id, cta_text, cta_url, new_tab, cta_color,
            cta_background, border_radius, cta_order
        ) VALUES (
            :banner_id, :cta_text, :cta_url, :new_tab, :cta_color,
            :cta_background, :border_radius, :cta_order
        )";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($ctas as $index => $cta) {
            $stmt->execute([
                ':banner_id' => $bannerId,
                ':cta_text' => $cta['text'] ?? '',
                ':cta_url' => $cta['url'] ?? '#',
                ':new_tab' => $cta['newTab'] ?? false,
                ':cta_color' => $cta['color'] ?? '#ffffff',
                ':cta_background' => $cta['background'] ?? '#1f2937',
                ':border_radius' => $cta['borderRadius'] ?? 8,
                ':cta_order' => $index
            ]);
        }
    }
    
    /**
     * Helper: Insert styling
     */
    private function insertStyling($bannerId, $styling) {
        $sql = "INSERT INTO banner_styling (
            banner_id, border_radius, shadow_x, shadow_y, shadow_blur,
            shadow_spread, shadow_color, shadow_opacity, padding_top,
            padding_right, padding_bottom, padding_left, margin_top,
            margin_right, margin_bottom, margin_left, z_index, opacity
        ) VALUES (
            :banner_id, :border_radius, :shadow_x, :shadow_y, :shadow_blur,
            :shadow_spread, :shadow_color, :shadow_opacity, :padding_top,
            :padding_right, :padding_bottom, :padding_left, :margin_top,
            :margin_right, :margin_bottom, :margin_left, :z_index, :opacity
        )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':banner_id' => $bannerId,
            ':border_radius' => $styling['borderRadius'] ?? 12,
            ':shadow_x' => $styling['boxShadow']['x'] ?? 0,
            ':shadow_y' => $styling['boxShadow']['y'] ?? 4,
            ':shadow_blur' => $styling['boxShadow']['blur'] ?? 12,
            ':shadow_spread' => $styling['boxShadow']['spread'] ?? 0,
            ':shadow_color' => $styling['boxShadow']['color'] ?? '#000000',
            ':shadow_opacity' => $styling['boxShadow']['opacity'] ?? 0.1,
            ':padding_top' => $styling['padding']['top'] ?? 24,
            ':padding_right' => $styling['padding']['right'] ?? 24,
            ':padding_bottom' => $styling['padding']['bottom'] ?? 24,
            ':padding_left' => $styling['padding']['left'] ?? 24,
            ':margin_top' => $styling['margin']['top'] ?? 0,
            ':margin_right' => $styling['margin']['right'] ?? 0,
            ':margin_bottom' => $styling['margin']['bottom'] ?? 0,
            ':margin_left' => $styling['margin']['left'] ?? 0,
            ':z_index' => $styling['zIndex'] ?? 1,
            ':opacity' => $styling['opacity'] ?? 1
        ]);
    }
    
    /**
     * Helper: Insert badge
     */
    private function insertBadge($bannerId, $badge) {
        $sql = "INSERT INTO banner_badges (banner_id, badge_text, badge_color) 
                VALUES (:banner_id, :badge_text, :badge_color)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':banner_id' => $bannerId,
            ':badge_text' => $badge['text'] ?? null,
            ':badge_color' => $badge['color'] ?? '#f59e0b'
        ]);
    }
    
    /**
     * Helper: Insert animations
     */
    private function insertAnimations($bannerId, $animations) {
        $sql = "INSERT INTO banner_animations (banner_id, hover_animation, click_animation) 
                VALUES (:banner_id, :hover_animation, :click_animation)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':banner_id' => $bannerId,
            ':hover_animation' => $animations['hover'] ?? 'none',
            ':click_animation' => $animations['click'] ?? 'none'
        ]);
    }
    
    /**
     * Helper: Get CTAs for a banner
     */
    private function getBannerCTAs($bannerId) {
        $sql = "SELECT * FROM banner_ctas WHERE banner_id = :banner_id ORDER BY cta_order";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':banner_id' => $bannerId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Helper: Delete related data
     */
    private function deleteRelatedData($bannerId) {
        $tables = ['banner_backgrounds', 'banner_ctas', 'banner_styling', 'banner_badges', 'banner_animations'];
        
        foreach ($tables as $table) {
            $sql = "DELETE FROM $table WHERE banner_id = :banner_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':banner_id' => $bannerId]);
        }
    }
    
    /**
     * Format single banner for frontend
     */
    private function formatBannerForFrontend($banner) {
        return [
            'id' => $banner['id'],
            'group' => $banner['group_name'],
            'title' => $banner['title'],
            'subtitle' => $banner['subtitle'],
            'description' => $banner['description'],
            'section' => $banner['section'],
            'layout' => $banner['layout'],
            'desktopImage' => $banner['desktop_image'],
            'mobileImage' => $banner['mobile_image'],
            'background' => [
                'type' => $banner['bg_type'],
                'color' => $banner['bg_color'],
                'gradient' => [
                    'color1' => $banner['gradient_color1'],
                    'color2' => $banner['gradient_color2'],
                    'angle' => $banner['gradient_angle']
                ]
            ],
            'textColor' => $banner['text_color'],
            'ctas' => $banner['ctas'] ?? [],
            'styling' => [
                'borderRadius' => $banner['border_radius'],
                'boxShadow' => [
                    'x' => $banner['shadow_x'],
                    'y' => $banner['shadow_y'],
                    'blur' => $banner['shadow_blur'],
                    'spread' => $banner['shadow_spread'],
                    'color' => $banner['shadow_color'],
                    'opacity' => $banner['shadow_opacity']
                ],
                'padding' => [
                    'top' => $banner['padding_top'],
                    'right' => $banner['padding_right'],
                    'bottom' => $banner['padding_bottom'],
                    'left' => $banner['padding_left']
                ],
                'margin' => [
                    'top' => $banner['margin_top'],
                    'right' => $banner['margin_right'],
                    'bottom' => $banner['margin_bottom'],
                    'left' => $banner['margin_left']
                ],
                'zIndex' => $banner['z_index'],
                'opacity' => $banner['opacity']
            ],
            'badge' => [
                'text' => $banner['badge_text'],
                'color' => $banner['badge_color']
            ],
            'status' => [
                'active' => (bool)$banner['is_active'],
                'showDesktop' => (bool)$banner['show_desktop'],
                'showTablet' => (bool)$banner['show_tablet'],
                'showMobile' => (bool)$banner['show_mobile'],
                'priority' => $banner['priority']
            ],
            'animations' => [
                'hover' => $banner['hover_animation'],
                'click' => $banner['click_animation']
            ]
        ];
    }
    
    /**
     * Format multiple banners for frontend
     */
    private function formatBannersForFrontend($banners) {
        return array_map([$this, 'formatBannerForFrontend'], $banners);
    }
}
?>