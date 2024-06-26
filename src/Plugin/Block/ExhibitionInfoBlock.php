<?php

namespace Drupal\galerie_23_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Views;

/**
 * Provides a 'Exhibition Info' block.
 *
 * @Block(
 *   id = "exhibition_info_block",
 *   admin_label = @Translation("Exhibition Info Block"),
 * )
 */

 class ExhibitionInfoBlock extends BlockBase implements ContainerFactoryPluginInterface {

    protected $entityTypeManager;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {

        parent::__construct($configuration, $plugin_id, $plugin_definition);

        $this->entityTypeManager = $entity_type_manager;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {

        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,
            $container->get('entity_type.manager')
        );
    }

    public function build() {
      $build = [];
      $route_match = \Drupal::routeMatch();
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

      if ($node = $route_match->getParameter('node')) {

        // Load the complete node object.
        if ($node instanceof \Drupal\node\NodeInterface) {
            $node = \Drupal::entityTypeManager()->getStorage('node')->load($node->id());
        }
        // Check if the node is of type 'exhibition'.
        if ($node && $node->bundle() === 'exhibition') {

          if ($node->hasTranslation($langcode)) {
            $node = $node->getTranslation($langcode);

            // Check if the exhibition type is '3' based on taxonomy term
            if ($node->get('field_typ')->referencedEntities()[0]->get('tid')->value == 3) {
              // Render accompanying program view
              $view = Views::getView('accompanying_program');

              if (is_object($view)) {
                $view->setDisplay('block');
                $build['view_output'] = $view->render();
              }

              // Process exhibition gallery images
              $images = $node->get('field_exhibition_galery')->referencedEntities();
              $rendered_images = [];

              foreach ($images as $image) {
                if ($image instanceof \Drupal\media\Entity\Media) {
                  $image_field = $image->get('field_media_image');

                  if ($image_field && !$image_field->isEmpty()) {
                    $file = $image_field->entity;

                    if ($file instanceof \Drupal\file\FileInterface) {
                      $rendered_images[] = [
                        $file,
                      ];
                    }
                  }
                }
              }

              // Process exhibition videos
              $videos = $node->get('field_video_gallery')->referencedEntities();
              $rendered_video = [];
              $video_urls = [];

              foreach ($videos as $video) {
                if ($video instanceof \Drupal\media\Entity\Media) {
                  $video_field = $video->get('field_media_oembed_video');

                  if ($video_field && !$video_field->isEmpty()) {
                    $video_url = $video_field->first()->getValue()['value'];
                    parse_str(parse_url($video_url, PHP_URL_QUERY), $query_params);

                    $rendered_video[] = $video_field->view('view_mode');

                    if (isset($query_params['v'])) {
                      $video_id = $query_params['v'];
                      $embed_url = "https://www.youtube.com/embed/{$video_id}";
                      $video_urls[] = $embed_url;
                    }
                  }
                }
              }

              // Process downloadable files
              $files_to_download_render_array = [];
              $files_to_download = $node->get('field_file_to_download')->referencedEntities();

              foreach ($files_to_download as $file) {
                if ($file instanceof \Drupal\file\FileInterface) {
                  $file_render_array = [
                    '#theme' => 'file_link',
                    '#file' => $file,
                    '#description' => $file->getFilename(),
                  ];

                  $files_to_download_render_array[] = $file_render_array;
                }
              }

              // Get boolean field values
              $is_sold_out = $node->get('field_sold_out')->value;
              $free_exhibition = $node->get('field_free_exhibition')->value;

//              $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
//
//              if ($node->hasTranslation($langcode)) {
//                $node = $node->getTranslation($langcode);
//              }

              // Building the render array for the block
              $build['node_fields'] = [
                'date' => $node->field_date->view(),
                'taxonomy' => $node->field_taxonomy->view(),
                'author' => $node->field_author->view(),
                'person' => $node->field_person->view(),
                'double_text' => $node->field_data_double_text->view(),
                'button' => $node->field_button->view(),
                'sold_out' => $is_sold_out,
                'free_exhibition' => $free_exhibition,
                'info' => $node->field_info->view(),
                'opening_hour' => $node->field_opening_hour->view(),
                'file_to_download' => $files_to_download_render_array,
                'field_related' => $node->field_related->view(),
                'exhibition_gallery' => $rendered_images,
                'video_gallery' => $rendered_video,
                'video_urls' => $video_urls,
              ];
            }
          }
        }
      }

      return $build;
    }

    public function blockAccess(AccountInterface $account) {

        $route_match = \Drupal::routeMatch();

        if ($node = $route_match->getParameter('node')) {

            if ($node instanceof \Drupal\node\NodeInterface) {
                $node = $this->entityTypeManager->getStorage('node')->load($node->id());
              }

            if ($node && $node->bundle() === 'exhibition' && (int) $node->get('field_typ')->referencedEntities()[0]->get('tid')->value == 3) {
                return AccessResult::allowed()->addCacheableDependency($node);
            }
        }
        return AccessResult::forbidden();
    }
 }
