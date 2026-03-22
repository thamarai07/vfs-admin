<?php
// APIController/BannerAPIController.php

class BannerAPIController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        if (!$this->db) {
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Get dynamic banners with improved response structure
     */
    public function getDynamicBanners($filters = []) {
        try {
            $device = $this->sanitizeDevice($filters['device'] ?? 'desktop');
            $city = $this->sanitizeString($filters['city'] ?? null);
            $pincode = $this->sanitizeString($filters['pincode'] ?? null);
            $user_segment = $this->sanitizeString($filters['user_segment'] ?? null);
            
            // Get active sections
            $sections = $this->getActiveSections($device, $city, $pincode, $user_segment);
            
            // Get banners (including those without section_id)
            $allBanners = $this->getAllBanners($device, $city, $pincode, $user_segment);
            
            if (empty($allBanners)) {
                return [
                    'success' => true,
                    'message' => 'No active banners found for the given filters',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'metadata' => [
                        'device' => $device,
                        'total_sections' => 0,
                        'total_banners' => 0,
                        'filters_applied' => [
                            'city' => $city,
                            'pincode' => $pincode,
                            'user_segment' => $user_segment
                        ]
                    ],
                    'data' => []
                ];
            }
            
            // Organize banners by section
            $formattedSections = [];
            $totalBanners = 0;
            
            // Group banners by section_id
            $bannersBySection = [];
            foreach ($allBanners as $banner) {
                $sectionId = $banner['section_id'] ?? 0; // 0 for null section_id
                if (!isset($bannersBySection[$sectionId])) {
                    $bannersBySection[$sectionId] = [];
                }
                $bannersBySection[$sectionId][] = $banner;
            }
            
            // Process sections with banners
            foreach ($sections as $section) {
                $sectionBanners = $bannersBySection[$section['section_id']] ?? [];
                
                if (empty($sectionBanners)) {
                    continue;
                }
                
                $totalBanners += count($sectionBanners);
                
                $formattedSections[] = [
                    'section_id' => $section['section_id'],
                    'section_name' => $section['section_name'],
                    'section_type' => $section['section_type'],
                    'display_order' => $section['display_order'],
                    'configuration' => [
                        'layout' => [
                            'max_columns' => $section['max_columns'],
                            'responsive_mobile_columns' => $section['responsive_mobile_columns'],
                            'responsive_desktop_columns' => $section['responsive_desktop_columns']
                        ],
                        'styling' => [
                            'background' => [
                                'color' => $section['background_color'],
                                'gradient' => $section['background_gradient'],
                                'image' => $section['background_image']
                            ],
                            'overlay' => [
                                'color' => $section['overlay_color'],
                                'opacity' => $section['overlay_opacity']
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top' => $section['padding']['top'],
                                    'bottom' => $section['padding']['bottom'],
                                    'left' => $section['padding']['left'],
                                    'right' => $section['padding']['right']
                                ],
                                'margin' => [
                                    'top' => $section['margin']['top'],
                                    'bottom' => $section['margin']['bottom']
                                ]
                            ]
                        ]
                    ],
                    'banners' => $sectionBanners,
                    'banner_count' => count($sectionBanners)
                ];
            }
            
            // Handle banners without section_id (create default section)
            if (isset($bannersBySection[0]) && !empty($bannersBySection[0])) {
                $defaultBanners = $bannersBySection[0];
                $totalBanners += count($defaultBanners);
                
                $formattedSections[] = [
                    'section_id' => 0,
                    'section_name' => 'Default Section',
                    'section_type' => 'default',
                    'display_order' => 999,
                    'configuration' => [
                        'layout' => [
                            'max_columns' => 4,
                            'responsive_mobile_columns' => 1,
                            'responsive_desktop_columns' => 4
                        ],
                        'styling' => [
                            'background' => [
                                'color' => null,
                                'gradient' => null,
                                'image' => null
                            ],
                            'overlay' => [
                                'color' => null,
                                'opacity' => 0
                            ],
                            'spacing' => [
                                'padding' => [
                                    'top' => 20,
                                    'bottom' => 20,
                                    'left' => 20,
                                    'right' => 20
                                ],
                                'margin' => [
                                    'top' => 0,
                                    'bottom' => 0
                                ]
                            ]
                        ]
                    ],
                    'banners' => $defaultBanners,
                    'banner_count' => count($defaultBanners)
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Banners retrieved successfully',
                'timestamp' => date('Y-m-d H:i:s'),
                'metadata' => [
                    'device' => $device,
                    'total_sections' => count($formattedSections),
                    'total_banners' => $totalBanners,
                    'filters_applied' => [
                        'city' => $city,
                        'pincode' => $pincode,
                        'user_segment' => $user_segment
                    ]
                ],
                'data' => $formattedSections
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve banners: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Get active sections
     */
    private function getActiveSections($device, $city, $pincode, $user_segment) {
        $sql = "SELECT 
                    section_id,
                    section_name,
                    section_type,
                    display_order,
                    background_color,
                    background_gradient,
                    background_image,
                    overlay_color,
                    overlay_opacity,
                    padding_top,
                    padding_bottom,
                    padding_left,
                    padding_right,
                    margin_top,
                    margin_bottom,
                    max_columns,
                    responsive_mobile_columns,
                    responsive_desktop_columns,
                    device_type,
                    target_cities,
                    target_pincodes,
                    target_user_segments,
                    is_active,
                    start_date,
                    end_date
                FROM banner_sections
                WHERE is_active = 1";
        
        $sql .= " AND (start_date IS NULL OR start_date <= NOW())";
        $sql .= " AND (end_date IS NULL OR end_date >= NOW())";
        $sql .= " AND (device_type = 'all' OR FIND_IN_SET(:device, device_type))";
        $sql .= " ORDER BY display_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':device', $device, PDO::PARAM_STR);
        $stmt->execute();
        
        $sections = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!$this->matchesLocationTarget($row, $city, $pincode)) {
                continue;
            }
            
            if (!$this->matchesUserSegment($row, $user_segment)) {
                continue;
            }
            
            $sections[] = [
                'section_id' => (int)$row['section_id'],
                'section_name' => $row['section_name'],
                'section_type' => $row['section_type'],
                'display_order' => (int)$row['display_order'],
                'background_color' => $row['background_color'],
                'background_gradient' => $row['background_gradient'],
                'background_image' => $row['background_image'],
                'overlay_color' => $row['overlay_color'],
                'overlay_opacity' => (float)$row['overlay_opacity'],
                'padding' => [
                    'top' => (int)$row['padding_top'],
                    'bottom' => (int)$row['padding_bottom'],
                    'left' => (int)$row['padding_left'],
                    'right' => (int)$row['padding_right']
                ],
                'margin' => [
                    'top' => (int)$row['margin_top'],
                    'bottom' => (int)$row['margin_bottom']
                ],
                'max_columns' => (int)$row['max_columns'],
                'responsive_mobile_columns' => (int)$row['responsive_mobile_columns'],
                'responsive_desktop_columns' => (int)$row['responsive_desktop_columns']
            ];
        }
        
        return $sections;
    }
    
    /**
     * Get all active banners (including those without section_id)
     */
    private function getAllBanners($device, $city, $pincode, $user_segment) {
        $sql = "SELECT 
                    b.id,
                    b.banner_id,
                    b.section_id,
                    b.display_order,
                    b.group_id,
                    b.group_name,
                    b.title,
                    b.subtitle,
                    b.description,
                    b.section,
                    b.layout,
                    b.desktop_image,
                    b.mobile_image,
                    b.text_color,
                    b.is_active,
                    b.show_desktop,
                    b.show_tablet,
                    b.show_mobile,
                    b.priority,
                    bg.bg_type,
                    bg.bg_color,
                    bg.gradient_color1,
                    bg.gradient_color2,
                    bg.gradient_angle,
                    bg.bg_image,
                    bs.border_radius,
                    bs.shadow_x,
                    bs.shadow_y,
                    bs.shadow_blur,
                    bs.shadow_spread,
                    bs.shadow_color,
                    bs.shadow_opacity,
                    bs.padding_top,
                    bs.padding_right,
                    bs.padding_bottom,
                    bs.padding_left,
                    bs.margin_top,
                    bs.margin_right,
                    bs.margin_bottom,
                    bs.margin_left,
                    bs.z_index,
                    bs.opacity
                FROM banners b
                LEFT JOIN banner_backgrounds bg ON b.banner_id = bg.banner_id
                LEFT JOIN banner_styling bs ON b.banner_id = bs.banner_id
                WHERE b.is_active = 1";
        
        // Device filter
        if ($device === 'mobile') {
            $sql .= " AND b.show_mobile = 1";
        } elseif ($device === 'tablet') {
            $sql .= " AND b.show_tablet = 1";
        } else {
            $sql .= " AND b.show_desktop = 1";
        }
        
        $sql .= " ORDER BY b.display_order ASC, b.priority DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $banners = [];
        $bannerIds = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bannerIds[] = $row['banner_id'];
            
            $banners[] = [
                'banner_id' => (int)$row['banner_id'],
                'section_id' => $row['section_id'] ? (int)$row['section_id'] : null,
                'display_order' => (int)$row['display_order'],
                'group_id' => $row['group_id'],
                'group_name' => $row['group_name'],
                'priority' => (int)$row['priority'],
                'content' => [
                    'title' => $row['title'],
                    'subtitle' => $row['subtitle'],
                    'description' => $row['description'],
                    'section' => $row['section']
                ],
                'layout' => $row['layout'],
                'images' => [
                    'desktop' => $row['desktop_image'],
                    'mobile' => $row['mobile_image']
                ],
                'styling' => [
                    'text_color' => $row['text_color'] ?? '#ffffff',
                    'background' => [
                        'type' => $row['bg_type'] ?? 'solid',
                        'color' => $row['bg_color'],
                        'gradient' => $row['bg_type'] === 'gradient' ? [
                            'color1' => $row['gradient_color1'],
                            'color2' => $row['gradient_color2'],
                            'angle' => (int)$row['gradient_angle']
                        ] : null,
                        'image' => $row['bg_image']
                    ],
                    'border' => [
                        'radius' => (int)($row['border_radius'] ?? 12)
                    ],
                    'shadow' => [
                        'x' => (int)($row['shadow_x'] ?? 0),
                        'y' => (int)($row['shadow_y'] ?? 4),
                        'blur' => (int)($row['shadow_blur'] ?? 12),
                        'spread' => (int)($row['shadow_spread'] ?? 0),
                        'color' => $row['shadow_color'] ?? '#000000',
                        'opacity' => (float)($row['shadow_opacity'] ?? 0.1)
                    ],
                    'spacing' => [
                        'padding' => [
                            'top' => (int)($row['padding_top'] ?? 24),
                            'right' => (int)($row['padding_right'] ?? 24),
                            'bottom' => (int)($row['padding_bottom'] ?? 24),
                            'left' => (int)($row['padding_left'] ?? 24)
                        ],
                        'margin' => [
                            'top' => (int)($row['margin_top'] ?? 0),
                            'right' => (int)($row['margin_right'] ?? 0),
                            'bottom' => (int)($row['margin_bottom'] ?? 0),
                            'left' => (int)($row['margin_left'] ?? 0)
                        ]
                    ],
                    'z_index' => (int)($row['z_index'] ?? 1),
                    'opacity' => (float)($row['opacity'] ?? 1.0)
                ],
                'animations' => null,
                'badge' => null,
                'ctas' => []
            ];
        }
        
        // Batch load related data
        if (!empty($bannerIds)) {
            $animations = $this->getBatchAnimations($bannerIds);
            $badges = $this->getBatchBadges($bannerIds);
            $ctas = $this->getBatchCTAs($bannerIds);
            
            // Attach related data
            foreach ($banners as &$banner) {
                $bannerId = $banner['banner_id'];
                $banner['animations'] = $animations[$bannerId] ?? null;
                $banner['badge'] = $badges[$bannerId] ?? null;
                $banner['ctas'] = $ctas[$bannerId] ?? [];
            }
        }
        
        return $banners;
    }
    
    /**
     * Batch load animations
     */
    private function getBatchAnimations($bannerIds) {
        if (empty($bannerIds)) return [];
        
        $placeholders = implode(',', array_fill(0, count($bannerIds), '?'));
        $sql = "SELECT banner_id, hover_animation, click_animation
                FROM banner_animations 
                WHERE banner_id IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bannerIds);
        
        $animations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $animations[$row['banner_id']] = [
                'hover' => $row['hover_animation'] ?? 'none',
                'click' => $row['click_animation'] ?? 'none'
            ];
        }
        
        return $animations;
    }
    
    /**
     * Batch load badges
     */
    private function getBatchBadges($bannerIds) {
        if (empty($bannerIds)) return [];
        
        $placeholders = implode(',', array_fill(0, count($bannerIds), '?'));
        $sql = "SELECT banner_id, badge_text, badge_color
                FROM banner_badges 
                WHERE banner_id IN ($placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bannerIds);
        
        $badges = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['badge_text'])) {
                $badges[$row['banner_id']] = [
                    'text' => $row['badge_text'],
                    'color' => $row['badge_color'] ?? '#f59e0b'
                ];
            }
        }
        
        return $badges;
    }
    
    /**
     * Batch load CTAs
     */
    private function getBatchCTAs($bannerIds) {
        if (empty($bannerIds)) return [];
        
        $placeholders = implode(',', array_fill(0, count($bannerIds), '?'));
        $sql = "SELECT banner_id, cta_text, cta_url, new_tab, cta_color, 
                       cta_background, border_radius, cta_order
                FROM banner_ctas 
                WHERE banner_id IN ($placeholders)
                ORDER BY banner_id, cta_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bannerIds);
        
        $ctas = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!isset($ctas[$row['banner_id']])) {
                $ctas[$row['banner_id']] = [];
            }
            
            $ctas[$row['banner_id']][] = [
                'text' => $row['cta_text'],
                'url' => $row['cta_url'],
                'new_tab' => (bool)$row['new_tab'],
                'styling' => [
                    'color' => $row['cta_color'] ?? '#ffffff',
                    'background' => $row['cta_background'] ?? '#3b82f6',
                    'border_radius' => (int)($row['border_radius'] ?? 8)
                ]
            ];
        }
        
        return $ctas;
    }
    
    /**
     * Location targeting
     */
    private function matchesLocationTarget($section, $city, $pincode) {
        if (empty($section['target_cities']) && empty($section['target_pincodes'])) {
            return true;
        }
        
        if (!empty($section['target_cities']) && $city) {
            $cities = array_map('trim', explode(',', strtolower($section['target_cities'])));
            if (!in_array(strtolower($city), $cities)) {
                return false;
            }
        }
        
        if (!empty($section['target_pincodes']) && $pincode) {
            $pincodes = array_map('trim', explode(',', $section['target_pincodes']));
            if (!in_array($pincode, $pincodes)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * User segment targeting
     */
    private function matchesUserSegment($section, $user_segment) {
        if (empty($section['target_user_segments'])) {
            return true;
        }
        
        if (!$user_segment) {
            return false;
        }
        
        $segments = array_map('trim', explode(',', strtolower($section['target_user_segments'])));
        return in_array(strtolower($user_segment), $segments);
    }
    
    /**
     * Sanitize device
     */
    private function sanitizeDevice($device) {
        $allowed = ['desktop', 'tablet', 'mobile'];
        return in_array($device, $allowed) ? $device : 'desktop';
    }
    
    /**
     * Sanitize string
     */
    private function sanitizeString($str) {
        return $str ? trim(strip_tags($str)) : null;
    }
}
?>