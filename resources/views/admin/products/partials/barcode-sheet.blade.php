<div class="barcode-document">
    @foreach($labelPages as $labels)
        <section class="barcode-page barcode-page--{{ $paperSize === \App\Enums\BarcodePaperSize::A4 ? 'a4' : 'thermal' }} {{ $loop->first ? '' : 'barcode-page--break-before' }}">
            @if($paperSize === \App\Enums\BarcodePaperSize::A4)
                <table class="barcode-label-grid">
                    <tbody>
                    @foreach(array_chunk($labels, $paperSize->columns()) as $row)
                        <tr>
                            @for($column = 0; $column < $paperSize->columns(); $column++)
                                <td class="barcode-label-cell">
                                    @if(isset($row[$column]))
                                        @include('admin.products.partials.barcode-label', ['label' => $row[$column]])
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @elseif(isset($labels[0]))
                @include('admin.products.partials.barcode-label', ['label' => $labels[0]])
            @endif
        </section>
    @endforeach
</div>
