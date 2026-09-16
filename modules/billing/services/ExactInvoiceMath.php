<?php
declare(strict_types=1);
namespace Billing\Services;

/** All inputs and outputs are decimal strings. No binary floating-point money. */
final class ExactInvoiceMath
{
    public static function decimal(mixed $value, int $scale = 6, bool $positive = false): string
    {
        if (!is_string($value) || !preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D', $value)) throw new \InvalidArgumentException('invalid_decimal');
        if ($positive && bccomp($value, '0', $scale) <= 0) throw new \InvalidArgumentException('non_positive_decimal');
        return bcadd($value, '0', $scale);
    }

    public static function round(string $value, int $scale): string
    {
        $offset = '0.'.str_repeat('0', $scale).'5';
        return bcadd($value, $value[0] === '-' ? '-'.$offset : $offset, $scale);
    }

    public static function compute(array $items): array
    {
        if ($items === [] || count($items) > 100) throw new \InvalidArgumentException('invalid_invoice_items');
        $subtotal = $discountTotal = $taxTotal = '0.000000';
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) throw new \InvalidArgumentException('invalid_invoice_item');
            $quantity = self::decimal($item['quantity'] ?? null, 6, true);
            $unitValue = self::decimal($item['unit_value'] ?? null);
            $discount = self::decimal($item['discount'] ?? '0');
            $lineSubtotal = self::round(bcmul($quantity, $unitValue, 12), 6);
            self::bounded($lineSubtotal);
            if (bccomp($discount, $lineSubtotal, 6) > 0) throw new \InvalidArgumentException('discount_exceeds_subtotal');
            $base = bcsub($lineSubtotal, $discount, 6);
            $lineTax = '0.000000';
            $taxRows = [];
            if(!is_array($item['taxes']??[]) || !array_is_list($item['taxes']??[]))throw new \InvalidArgumentException('invalid_invoice_tax');
            foreach (($item['taxes'] ?? []) as $tax) {
                if (!is_array($tax)) throw new \InvalidArgumentException('invalid_invoice_tax');
                $direction = $tax['direction'] ?? '';
                $factor = $tax['factor_code'] ?? '';
                if (!in_array($direction, ['TRANSFER','WITHHOLD'], true) || !in_array($factor, ['Tasa','Cuota','Exento'], true)) throw new \InvalidArgumentException('invalid_invoice_tax');
                $rate = $factor === 'Exento' ? null : self::decimal($tax['rate'] ?? null);
                $taxBase=$factor==='Cuota'?$quantity:$base;
                $amount = $rate === null ? null : self::round(bcmul($taxBase, $rate, 12), 6);
                if($amount!==null)self::bounded($amount);
                $signed = $amount === null ? '0.000000' : ($direction === 'WITHHOLD' ? '-'.$amount : $amount);
                $lineTax = bcadd($lineTax, $signed, 6);
                $taxRows[] = ['direction'=>$direction,'tax_code'=>$tax['tax_code'] ?? '', 'factor_code'=>$factor,
                    'rate'=>$rate,'tax_base'=>$taxBase,'amount'=>$amount];
            }
            $lineTotal = bcadd($base, $lineTax, 6);
            self::bounded($lineTotal);
            if (bccomp($lineTotal, '0', 6) < 0) throw new \InvalidArgumentException('negative_line_total');
            $subtotal = bcadd($subtotal, $lineSubtotal, 6);
            $discountTotal = bcadd($discountTotal, $discount, 6);
            $taxTotal = bcadd($taxTotal, $lineTax, 6);
            $result[] = $item + ['taxes'=>[]];
            $last = count($result)-1;
            $result[$last]['quantity']=$quantity;
            $result[$last]['unit_value']=$unitValue;
            $result[$last]['discount']=$discount;
            $result[$last]['line_subtotal']=$lineSubtotal;
            $result[$last]['line_total']=$lineTotal;
            $result[$last]['taxes']=$taxRows;
        }
        $total=bcadd(bcsub($subtotal,$discountTotal,6),$taxTotal,6);
        foreach([$subtotal,$discountTotal,$taxTotal,$total] as $value)self::bounded($value);
        if (bccomp($total, '0', 6) < 0) throw new \InvalidArgumentException('negative_invoice_total');
        return ['items'=>$result,'subtotal'=>$subtotal,'discount'=>$discountTotal,'tax_total'=>$taxTotal,'total'=>$total];
    }

    private static function bounded(string $value):void
    {
        if(bccomp($value,'999999999999.999999',6)>0 || bccomp($value,'-999999999999.999999',6)<0)throw new \InvalidArgumentException('invoice_amount_overflow');
    }
}
