<?php
/**
 * @file
 * Contains \Drupal\lao_find_my_mpp\Plugin\Block\LaoFindMyMppBlock.
 */

namespace Drupal\lao_find_my_mpp\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides a 'lao_find_my_mpp' block.
 *
 * @Block(
 *  id = "lao_find_my_mpp",
 *  admin_label = @Translation("Find your MPP "),
 *  category = @Translation("Custom Find My MPP ")
 * )
 */
class LaoFindMyMppBlock extends BlockBase{

    /**
     * {@inheritdoc}
     */
    public function build(){

        return \Drupal::formBuilder()->getForm('Drupal\lao_find_my_mpp\Form\LaoFindMyMppForm');
    }
}