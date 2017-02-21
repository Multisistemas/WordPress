<?php
/*
Plugin Name: WooCommerce Pagadito Payment Gateway
Plugin URI: http://dev.pagadito.com
Description: Pagadito Payment gateway for woocommerce
Version: 2.0.1
Author: Pagadito Developers
Author URI: https://www.pagadito.com
*/

/**
 * Pagadito.
 * 
 * Pagadito Payment Gateway Para WooCommerce (Wordpress) v1.0.1
 * 
 * Este programa es Software Libre: Usted puede redistribuirlo y/o modificarlo
 * bajo los terminos de la Licencia Publica General Reducida de GNU (GNU Lesser
 * Public Licence), tal como se encuentra publicada por la Free Software
 * Foundation, ya sea por su version 3 o cualquier otra version superior.
 * 
 * Este programa es distribuido en el espiritu de que sea util, pero SIN NINGUNA
 * GARANTIA; sin tampoco garantia implicita de MERCANTIBILIDAD o ADAPTABILIDAD
 * PARA UN USO PARTICULAR. Vea la licencia GNU LGPL para mayores detalles.
 * 
 * @category    Local
 * @package     Pagadito
 * @copyright   Copyright (c) 2013 - Pagadito, S. de R.L. (https://www.pagadito.com)
 * @license     http://www.gnu.org/licenses/lgpl.html
 * @author      Pagadito Development Team <developers@pagadito.com>
 */

require_once 'API/Pagadito.php';

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'woocommerce_pagadito_init' );

function woocommerce_pagadito_init() {
    class WC_Gateway_Pagadito extends WC_Payment_Gateway {
        public function __construct(){
            global $woocommerce;
            
            $this->id                 = "pagadito";
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = __( 'Pagadito', 'woocommerce' );
            $this->method_description = 'Pagadito funciona enviando a los clientes al sitio web de Pagadito, donde pueden introducir su información de pago, posteriormente el cliente es devuelto a su sitio web para completar la orden.';
            
            $this->init_form_fields();
            $this->init_settings();
            
            /* Cargando configuraciones */
            $this->title              = $this->get_option( 'title' );
            $this->icon_type          = $this->get_option( 'icon_type' );
            $this->description        = $this->get_option( 'description' );
            $this->uid                = $this->get_option( 'uid' );
            $this->wsk                = $this->get_option( 'wsk' );
            $this->sandbox_mode       = $this->get_option( 'sandbox_mode' );
            $this->debug              = $this->get_option( 'debug' );
            
            $this->icon               = apply_filters( 'woocommerce_pagadito_icon', $woocommerce->plugin_url() . '/../pagadito/assets/images/icons/'.$this->icon_type.'.png' );
            
            // Guardar logs
            /*if ( 'yes' == $this->debug )
                $this->log=new WC_Logger();*/
            
            // hook para opciones administrativas
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            // hook para respuesta de pagadito
            add_action( 'woocommerce_api_wc_gateway_pagadito', array( $this, 'check_pagadito_response' ) );
            
            // Desactivar si no esta disponible alguna de las monedas oficiales de pagadito
            if ( !$this->is_valid_for_use() ) $this->enabled = false;
        }

        /**
         * Registrar en Log
         * Verifica antes si debug esta activo para permitir guardar en en archivo
         * @param  string $mensaje Información a registrar en log
         */
        public function registrar_log( $order, $mensaje ) {
            if ( $this->debug ) {
                if ( empty( $this->log ) ) {
                    $this->log = new WC_Logger();
                }
                $this->log->add( 'pagadito', 'order:'.$order.' - '.$mensaje );
            }
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Activar/Desactivar', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Activar pagos con Pagadito.', 'woocommerce' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Titulo', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Controla el título que verán los usuarios en el proceso de pago, si se encuentra vacío sólo aparecerá el logo.', 'woocommerce' ),
                    'default' => __( 'Pagadito', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'description' => array(
                    'title' => __( 'Mensaje al cliente', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'Este control muestra la descripción que el usuario ve durante el pago.', 'woocommerce' ),
                    'default' => __( 'Pagadito le permite Pagar en línea de una manera segura, fácil y confiable.', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'icon_type' => array(
                    'title' => __( 'Logo Pagadito', 'woocommerce' ),
                    'type' => 'select',
                    'label' => __('Seleccione el tipo de ícono a mostrar en la pantalla de pago.', 'woocommerce'),
                    'description' => __( 'Logo que aparecerá en la pantalla de pago.', 'woocommerce' ),
                    'default' => 'color',
                    'options' => array(
                        'color' => __('A colores', 'woocommerce'),
                        'white' => __('Blanco', 'woocommerce'),
                        'black' => __('Negro', 'woocommerce'),
                    ),
                ),
                'debug' => array(
                    'title' => __( 'Depuraci&oacute;n', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Activar los registros de depuración', 'woocommerce' ),
                        'default' => 'yes',
                        'description' => sprintf( __( 'La API registrará todos los eventos en el archivo <code>%s</code>', 'woocommerce' ), wc_get_log_file_path( 'pagadito' )
                    ),
                ),
                'api_details' => array(
                    'title'       => __( 'Credenciales de conexión', 'woocommerce' ),
                    'type'        => 'title',
                    'description' => sprintf( __( 'Aprenda como acceder a sus credenciales de Pagadito %saquí%s.', 'woocommerce' ), '<a href="https://dev.pagadito.com/index.php?mod=docs&hac=conf" target="_blank">', '</a>' ),
                ),
                'uid' => array(
                    'title' => __( 'UID', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'UID de conexión puede encontrarlo en su Cuenta Pagadito.', 'woocommerce' ),
                    'default' => __( '', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'wsk' => array(
                    'title' => __( 'WSK', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( 'WSK de conexión puede encontrarlo en su Cuenta Pagadito.', 'woocommerce' ),
                    'default' => __( '', 'woocommerce' ),
                    'desc_tip'      => true,
                ),
                'sandbox_mode' => array(
                    'title' => __( 'Pagadito Sandbox', 'woocommerce' ),
                    'type' => 'checkbox',
                    'label' => __( 'Activar ambiente de pruebas', 'woocommerce' ),
                    'description' => 'Pagadito Sandbox es una herramienta que le permitirá hacer pagos como si fueran reales, para ello necesitará una cuenta comercial de prueba <a href="https://sandbox.pagadito.com/index.php?mod=user&hac=vregfC" target="_blank">Regístrese aquí</a>. Una vez finalizadas las pruebas asegúrese de deshabilitar esta opción ya que estará listo para procesar pagos en ambiente de Producción.',
                    'default' => 'yes'
                ),
            );
        }
        
        private function is_valid_for_use() {
            if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_pagadito_supported_currencies', array( 'GTQ', 'USD', 'HNL', 'NIO', 'CRC', 'PAB', 'DOP' ) ) ) ) return false;

            return true;
        }
        /**
         * Procesar Pago
         * @param  integer $order_id La orden que será procesada por Pagadito
         * @return array Devuelve la url a la que debe direccionar, en caso de error lo muestra en pantalla
         */
        public function process_payment( $order_id ){
            global $woocommerce;
            $order = new WC_Order( $order_id );
            
            $tax_included = get_option( 'woocommerce_prices_include_tax'  == 'yes')? true: false;
            
            try{
                $this->registrar_log( $order->get_order_number(), '' );
                $this->registrar_log( $order->get_order_number(), 'Procesando Pago' );

                //Inicializando api de pagadito
                $pg = new Pagadito($this->uid, $this->wsk);

                if ($this->sandbox_mode == 'yes'){
                    $pg->mode_sandbox_on();
                    $this->registrar_log( $order->get_order_number(), 'Transaccion en modo sandbox activado' );
                }

                if (!$pg->connect())
                    throw new Exception("Connect (".$pg->get_rs_code()."): ".$pg->get_rs_message());

                $pg->enable_pending_payments();
                $token_pagadito = $pg->get_rs_value();
                $this->registrar_log( $order->get_order_number(), 'Token de conexión = '.$pg->get_rs_value() );
                
                $moneda = strtoupper(get_woocommerce_currency());
                $this->registrar_log( $order->get_order_number(), 'La orden se procesa en moneda= '.$moneda );

                //Cambiando tipo de moneda
                switch ($moneda) {
                    case 'GTQ':
                        $pg->change_currency_gtq();
                        break;
                    case 'USD':
                        $pg->change_currency_usd();
                        break;
                    case 'HNL':
                        $pg->change_currency_hnl();
                        break;
                    case 'NIO':
                        $pg->change_currency_nio();
                        break;
                    case 'CRC':
                        $pg->change_currency_crc();
                        break;
                    case 'PAB':
                        $pg->change_currency_pab();
                        break;
                    case 'DOP':
                        $pg->change_currency_dop();
                        break;
                }
                
                if($order->get_item_count() <= 0)
                    throw new Exception("No hay items en la orden");
                
                // Agregando items
                foreach ($order->get_items() as $item){
                    if($tax_included)
                        $pg->add_detail($item['qty'], $item['name'], $order->get_item_subtotal( $item, true, false ));
                    else
                        $pg->add_detail($item['qty'], $item['name'], $order->get_item_subtotal( $item, false, true ));
                }
                
                // Aplicando fees
                $fees = $order->get_fees();
                if(is_array($fees) && count($fees) > 0){
                    foreach ($fees as $fee) {
                        $pg->add_detail(1, $fee['name'], $fee['line_total']);
                    }
                }
                                  
                // Aplicando shipping
                $shipping = $order->get_total_shipping();
                $shipping_tax = $order->get_shipping_tax();

                if((abs($shipping) - 0) >= 0.01){
                    $pg->add_detail(1, __( 'Shipping vía', 'woocommerce' ) . ' ' . ucwords( $order->get_shipping_method() ), $shipping);
                }
                
                // Aplicando impuestos (si los hay)
                $impuestos = $order->get_total_tax();
                if((abs($impuestos) - 0 ) >= 0.01){
                    $pg->add_detail(1, "Impuestos Totales", $impuestos);
                }
                // Aplicando descuento
                $discount = $order->get_order_discount() + $order->get_cart_discount();

                if((abs($discount) - 0) >= 0.01){
                    $discount = abs($discount) * (-1);
                    $pg->add_detail(1, "Descuento (".implode("+", $order->get_used_coupons()).")", $discount);
                }

                //Continuar con Pagadito
                $this->registrar_log( $order->get_order_number(), 'Continuando con Pagadito...' );
                $redirect_url = $pg->exec_trans($order->id);
                
                if ($redirect_url === false) {
                    throw new Exception("Exec trans (".$pg->get_rs_code()."): ".$pg->get_rs_message());
                }

                // Proceso realizado con exito
                $result = array(
                        'result' => 'success',
                        'redirect' => $redirect_url
                );
                
                // Asociando transaccion pagadito con la orden
                add_post_meta($order->id, '_token_pagadito', $token_pagadito);

                return $result;
            }catch(Exception $e){
                $this->registrar_log( $order->get_order_number(), 'Exception = '. $e->getMessage().' | CURL error = '. $pg->curl_errorno );
                wc_add_notice( '<strong>Ha ocurrido un problema</strong>:' . __( $e->getMessage(), 'woocommerce' ), 'error' );
            }
        }
        /**
         * Verificar Pago
         * Redirecciona a la pantalla de Orden completada y en caso de error direcciona a orden cancelada.
         */
        public function check_pagadito_response(){
            global $woocommerce;
            
            //Estados de transacciones en los que se permite realizar la comprobacion de pago
            $allowed_transaction_states = Array(
                'pending',
                'processing',
            );
            
            $values = stripslashes_deep( $_GET );
            $token_pagadito = '';
            try{
                if(!isset($values['token'], $values['order_id']))
                    throw new Exception("Se intento verificar una transaccion con token u order_id nulo");

                //Cargando la orden
                $order = new WC_Order($values['order_id']);
                $this->registrar_log( $order->get_order_number(), '...Regresó de Pagadito' );
                if (!in_array($order->status, $allowed_transaction_states))
                    throw new Exception("La transaccion no posee un estado valido para ser procesada: ".$order->status);
                
                // Redireccionar a pagina de gracias en caso que la orden ya haya sido procesada
                if ($order->status == 'processing'){
                    wp_redirect(html_entity_decode($order->get_checkout_order_received_url()));
                    return;
                }
                
                // Obtener el token pagadito, por la URL o por el campo personalizado
                if (!empty($values['token'])) {
                    $token_pagadito = $values['token'];
                } else {
                    $token_pagadito = end(get_post_meta( $order->id, '_token_pagadito' ));
                }
                
                if($token_pagadito === false)
                    throw new Exception("No se encontro el Token Pagadito");
                
                if($token_pagadito != $values['token'])
                    throw new Exception("Token Pagadito asociado a la orden no corresponde con el parametro get: '".$token_pagadito."' != '".$values['token']."'");
                
                $pg = new Pagadito($this->uid, $this->wsk);
                
                if($this->sandbox_mode == 'yes'){
                    $pg->mode_sandbox_on();
                    $this->registrar_log( $order->get_order_number(), 'Modo sandbox activado' );
                }
                
                if(!$pg->connect())
                    throw new Exception("Connect (".$pg->get_rs_code()."): ".$pg->get_rs_message());

                if(!$pg->get_status($token_pagadito))
                    throw new Exception("Get Status (".$pg->get_rs_code()."): ".$pg->get_rs_message());
                $respuesta_pg = $pg->get_rs_status();
                $this->registrar_log( $order->get_order_number(), 'Comprobando pago, estado = '.$respuesta_pg );

                switch ($respuesta_pg) {
                    case 'COMPLETED':
                        $order->add_order_note( __( 'Pago Completado, referencia de pago: '.$pg->get_rs_reference(), 'woocommerce' ), 1 );
                        $order->payment_complete();
                        wp_redirect(html_entity_decode($order->get_checkout_order_received_url()));
                        return;
                        break;
                    case 'VERIFYING':
                        $order->add_order_note( __( 'Pago En Verificación, referencia de pago: ' . $pg->get_rs_reference(), 'woocommerce' ), 1 );
                        $order->update_status('wc-on-hold', 'El pago quedo en verificación.');
                        wp_redirect(html_entity_decode($order->get_checkout_order_received_url()));
                        return;
                        break;
                    default:
                        $order->add_order_note('Transaccion no completada, estado: '.$respuesta_pg);
                        wp_redirect(html_entity_decode($order->get_cancel_order_url()));
                        return;
                        break;
                }
                exit;
            }catch(Exception $e){
                $this->registrar_log( $order->get_order_number(), 'Verificando pago - '.$e->getMessage() );
                $order->add_order_note('Error en el procesamiento de la orden: '. $e->getMessage());

                //Redireccionando a pantalla de error
                wp_redirect(html_entity_decode($order->get_cancel_order_url()));
                return;
            }
                
            return false;
        }
        
        public function get_return_url( $order = '', $page = 'thanks') {
            $thanks_page_id = woocommerce_get_page_id($page);
            if ( $thanks_page_id ) :
                $return_url = get_permalink($thanks_page_id);
            else :
                $return_url = home_url();
            endif;

            if ( $order ) :
                $return_url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, $return_url ) );
            endif;

            if ( is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes' )
                $return_url = str_replace( 'http:', 'https:', $return_url );

            return apply_filters( 'woocommerce_get_return_url', $return_url );
                }
    }

    function add_pagadito_gateway( $methods ) {
        $methods[] = 'WC_Gateway_Pagadito'; 
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_pagadito_gateway' );
}
