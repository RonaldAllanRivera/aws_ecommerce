<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static UnitEnum|string|null $navigationGroup = 'Checkout';

    public static function form(Schema $schema): Schema
    {
        // Read-only resource: no create/edit forms.
        return $schema->components([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')
                ->schema([
                    Infolists\Components\TextEntry::make('order_number')
                        ->label('Order #'),
                    Infolists\Components\TextEntry::make('email'),
                    Infolists\Components\TextEntry::make('customer_name')
                        ->label('Customer'),
                    Infolists\Components\TextEntry::make('shipping_address')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('shipping_method'),
                    Infolists\Components\TextEntry::make('status'),
                    Infolists\Components\TextEntry::make('subtotal')
                        ->money('usd')
                        ->label('Subtotal'),
                    Infolists\Components\TextEntry::make('tax')
                        ->money('usd')
                        ->label('Tax'),
                    Infolists\Components\TextEntry::make('shipping')
                        ->money('usd')
                        ->label('Shipping'),
                    Infolists\Components\TextEntry::make('total')
                        ->money('usd')
                        ->label('Total'),
                    Infolists\Components\TextEntry::make('created_at')
                        ->dateTime(),
                ])
                ->columns(2),
            Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name_snapshot')
                                ->label('Product'),
                            Infolists\Components\TextEntry::make('quantity'),
                            Infolists\Components\TextEntry::make('unit_price_snapshot')
                                ->money('usd')
                                ->label('Unit price'),
                            Infolists\Components\TextEntry::make('line_total')
                                ->money('usd')
                                ->label('Line total'),
                        ])
                        ->columns(4),
                ]),
            Section::make('Payment')
                ->schema([
                    Infolists\Components\TextEntry::make('payment.provider')
                        ->label('Provider'),
                    Infolists\Components\TextEntry::make('payment.status')
                        ->label('Status'),
                    Infolists\Components\TextEntry::make('payment.amount')
                        ->money('usd')
                        ->label('Amount'),
                    Infolists\Components\TextEntry::make('payment.provider_reference')
                        ->label('Reference'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'paid',
                        'warning' => 'pending',
                        'danger' => 'failed',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money('usd')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Created from'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
